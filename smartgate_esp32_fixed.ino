#include <WiFi.h>
#include <HTTPClient.h>
#include <ESP32Servo.h>

// ============================================================
//                    WIFI SETTINGS
// ============================================================

const char* WIFI_SSID = "CVAccessWifi (ThelmaDayrit)";
const char* WIFI_PASSWORD = "archimedesprototype001";


// ============================================================
//                    XAMPP SERVER
// ============================================================

const char* SERVER_URL =
  "http://192.168.100.101/fcapstone/scan.php";

const char* NOTIFICATION_URL =
  "http://192.168.100.101/fcapstone/send_notification.php";

// Must match SMARTGATE_DEVICE_KEY configured in Apache.
const char* DEVICE_KEY =
  "Y-q42R_nty9coWVlPO0gU03GKTW5tIJFtw73BacFI7NCX8EjNSOjeA-TYp1ygYQS";


// ============================================================
//                    SERVO OBJECTS
// ============================================================

Servo servoIN;
Servo servoOUT;


// ============================================================
//                    PIN ASSIGNMENTS
// ============================================================

// IN side
const int servoINPin = 27;
const int buttonINPin = 26;

// OUT side
const int servoOUTPin = 25;
const int buttonOUTPin = 33;

// Active buzzer
const int buzzerPin = 4;


// ============================================================
//                    GM861 QR SCANNER
// ============================================================

// GM861 TXD -> ESP32 GPIO 16
// GM861 RXD -> ESP32 GPIO 17
 
#define GM861_RX 16
#define GM861_TX 17

HardwareSerial GM861(2);


// ============================================================
//                    SERVO SETTINGS
// ============================================================

const int CLOSED_ANGLE = 90;
const int OPEN_ANGLE = 0;

const unsigned long NORMAL_OPEN_TIME = 2000;


// ============================================================
//                    EMERGENCY SETTINGS
// ============================================================

const unsigned long LONG_PRESS_TIME = 3000;

bool emergencyMode = false;


// ============================================================
//                    BUTTON VARIABLES
// ============================================================

bool inButtonActive = false;
bool outButtonActive = false;

bool inEmergencyTriggered = false;
bool outEmergencyTriggered = false;

unsigned long inButtonStartTime = 0;
unsigned long outButtonStartTime = 0;


// ============================================================
//                    QR VARIABLES
// ============================================================

String qrData = "";

String lastQR = "";

unsigned long lastQRTime = 0;

// Prevent same QR from being processed repeatedly
const unsigned long QR_COOLDOWN = 3000;

// Ignore repeated frames while the same QR remains in front of the scanner.
// The scanner becomes ready again after no serial data is received for this gap.
String lockedQR = "";
bool scannerReady = true;
unsigned long lastScannerByteAt = 0;
const unsigned long SCANNER_RELEASE_GAP = 3000;


// ============================================================
//                    BUZZER FUNCTIONS
// ============================================================

// ------------------------------------------------------------
// VALID QR
// One short beep
// ------------------------------------------------------------

void validQRBeep() {

  Serial.println("VALID QR - BEEP");

  digitalWrite(buzzerPin, HIGH);

  delay(100);

  digitalWrite(buzzerPin, LOW);
}


// ------------------------------------------------------------
// INVALID QR
// Three short beeps
// ------------------------------------------------------------

void invalidQRBeep() {

  Serial.println();
  Serial.println("**************************************");
  Serial.println("          ACCESS DENIED");
  Serial.println("        STUDENT NOT FOUND");
  Serial.println("**************************************");

  for (int i = 0; i < 3; i++) {

    digitalWrite(buzzerPin, HIGH);

    delay(250);

    digitalWrite(buzzerPin, LOW);

    delay(150);
  }
}


// ------------------------------------------------------------
// NETWORK ERROR
// One long beep
// ------------------------------------------------------------

void networkErrorBeep() {

  Serial.println();
  Serial.println("NETWORK ERROR");

  digitalWrite(buzzerPin, HIGH);

  delay(800);

  digitalWrite(buzzerPin, LOW);
}


// ============================================================
//                    WIFI CONNECTION
// ============================================================

void connectWiFi() {

  Serial.println();
  Serial.println("======================================");
  Serial.println("          CONNECTING TO WIFI");
  Serial.println("======================================");

  Serial.print("SSID: ");
  Serial.println(WIFI_SSID);

  WiFi.mode(WIFI_STA);

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;

  while (WiFi.status() != WL_CONNECTED &&
         attempts < 30) {

    delay(500);

    Serial.print(".");

    attempts++;
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {

    Serial.println("WIFI CONNECTED");

    Serial.print("ESP32 IP ADDRESS: ");
    Serial.println(WiFi.localIP());

    Serial.print("XAMPP SERVER: ");
    Serial.println(SERVER_URL);

  }

  else {

    Serial.println("WIFI CONNECTION FAILED");

    Serial.println("Check Wi-Fi name and password.");
  }

  Serial.println("======================================");
}


// ============================================================
//                    OPEN IN SERVO
// ============================================================

void openIN() {

  if (emergencyMode) {

    Serial.println("EMERGENCY MODE ACTIVE");
    Serial.println("NORMAL ACCESS BLOCKED");

    return;
  }

  Serial.println();
  Serial.println("--------------------------------------");
  Serial.println("DIRECTION: IN");
  Serial.println("IN SERVO -> OPEN");
  Serial.println("--------------------------------------");

  servoIN.write(OPEN_ANGLE);

  delay(NORMAL_OPEN_TIME);

  servoIN.write(CLOSED_ANGLE);

  Serial.println("IN SERVO -> CLOSED");
}


// ============================================================
//                    OPEN OUT SERVO
// ============================================================

void openOUT() {

  if (emergencyMode) {

    Serial.println("EMERGENCY MODE ACTIVE");
    Serial.println("NORMAL ACCESS BLOCKED");

    return;
  }

  Serial.println();
  Serial.println("--------------------------------------");
  Serial.println("DIRECTION: OUT");
  Serial.println("OUT SERVO -> OPEN");
  Serial.println("--------------------------------------");

  servoOUT.write(OPEN_ANGLE);

  delay(NORMAL_OPEN_TIME);

  servoOUT.write(CLOSED_ANGLE);

  Serial.println("OUT SERVO -> CLOSED");
}


// ============================================================
//                    EMERGENCY OPEN
// ============================================================

void emergencyOpen() {

  emergencyMode = true;

  servoIN.write(OPEN_ANGLE);

  servoOUT.write(OPEN_ANGLE);

  Serial.println();
  Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
  Serial.println("          EMERGENCY MODE ON");
  Serial.println("          BOTH SERVOS OPEN");
  Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
}


// ============================================================
//                    EMERGENCY CLOSE
// ============================================================

void emergencyClose() {

  emergencyMode = false;

  servoIN.write(CLOSED_ANGLE);

  servoOUT.write(CLOSED_ANGLE);

  Serial.println();
  Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
  Serial.println("          EMERGENCY MODE OFF");
  Serial.println("          BOTH SERVOS CLOSED");
  Serial.println("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
}


// ============================================================
//                    SEND EMAIL NOTIFICATION
// ============================================================

String getJsonStringValue(String json, String key) {

  String searchKey = "\"" + key + "\":\"";
  int start = json.indexOf(searchKey);

  if (start < 0) {
    return "";
  }

  start += searchKey.length();

  int end = json.indexOf("\"", start);

  if (end < 0) {
    return "";
  }

  return json.substring(start, end);
}


void sendNotification(
  String studentID,
  String direction,
  String scanTime
) {

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("EMAIL NOTIFICATION SKIPPED - WIFI DISCONNECTED");
    return;
  }

  HTTPClient notificationHttp;

  notificationHttp.begin(NOTIFICATION_URL);
  notificationHttp.addHeader("Content-Type", "application/json");
  notificationHttp.addHeader("X-Device-Key", DEVICE_KEY);

  notificationHttp.setConnectTimeout(3000);
  notificationHttp.setTimeout(20000);

  String jsonData =
    "{\"student_id\":\"" + studentID +
    "\",\"direction\":\"" + direction +
    "\",\"scan_time\":\"" + scanTime + "\"}";

  Serial.println();
  Serial.println("--------------------------------------");
  Serial.println("SENDING PARENT NOTIFICATION");
  Serial.println("--------------------------------------");

  int httpCode = notificationHttp.POST(jsonData);

  if (httpCode > 0) {

    String response = notificationHttp.getString();

    Serial.print("NOTIFICATION HTTP CODE: ");
    Serial.println(httpCode);

    Serial.print("NOTIFICATION RESPONSE: ");
    Serial.println(response);

    if (httpCode == 200 &&
        response.indexOf("\"success\":true") >= 0) {

      Serial.println("PARENT NOTIFICATION SENT");

    } else {

      Serial.println("PARENT NOTIFICATION FAILED");
    }

  } else {

    Serial.print("NOTIFICATION REQUEST FAILED: ");
    Serial.println(httpCode);
  }

  notificationHttp.end();

  Serial.println("--------------------------------------");
}


// ============================================================
//                    SEND QR TO SERVER
// ============================================================

void sendStudentToServer(String studentID) {

  studentID.trim();

  if (studentID.length() == 0) {
    return;
  }


  // ========================================================
  // CHECK WIFI
  // ========================================================

  if (WiFi.status() != WL_CONNECTED) {

    Serial.println();
    Serial.println("WIFI DISCONNECTED");

    connectWiFi();

    if (WiFi.status() != WL_CONNECTED) {

      Serial.println("CANNOT CONTACT XAMPP");

      networkErrorBeep();

      return;
    }
  }


  // ========================================================
  // DUPLICATE SCAN PROTECTION
  // ========================================================

  unsigned long currentTime = millis();

  if (studentID == lastQR &&
      currentTime - lastQRTime < QR_COOLDOWN) {

    Serial.println();
    Serial.println("DUPLICATE QR SCAN");
    Serial.println("SCAN IGNORED");

    return;
  }

  lastQR = studentID;
  lastQRTime = currentTime;


  Serial.println();
  Serial.println("======================================");
  Serial.println("             QR SCANNED");
  Serial.println("======================================");

  Serial.print("STUDENT ID: ");
  Serial.println(studentID);

  Serial.print("REQUEST: ");
  Serial.println(SERVER_URL);
  Serial.println("METHOD: POST");


  // ========================================================
  // HTTP REQUEST
  // ========================================================

  HTTPClient http;

  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Device-Key", DEVICE_KEY);

  String jsonData =
    "{\"qr_code\":\"" + studentID + "\"}";

  // Short timeout
  http.setConnectTimeout(2000);
  http.setTimeout(3000);

  unsigned long requestStart = millis();

  int httpCode = http.POST(jsonData);

  unsigned long requestTime =
    millis() - requestStart;


  // ========================================================
  // CHECK HTTP RESULT
  // ========================================================

  if (httpCode > 0) {

    Serial.print("HTTP RESPONSE CODE: ");
    Serial.println(httpCode);

    Serial.print("SERVER RESPONSE TIME: ");
    Serial.print(requestTime);
    Serial.println(" ms");


    String response = http.getString();

    Serial.println();
    Serial.println("SERVER RESPONSE:");
    Serial.println(response);


    // ======================================================
    // HTTP 200
    // ======================================================

    if (httpCode == 200) {


      // ====================================================
      // STUDENT FOUND / VALID QR
      // ====================================================

      if (response.indexOf("\"success\":true") >= 0) {

        Serial.println();
        Serial.println("======================================");
        Serial.println("         STUDENT VERIFIED");
        Serial.println("         ACCESS GRANTED");
        Serial.println("======================================");


        // --------------------------------------------------
        // GET DATA FROM SERVER RESPONSE
        // --------------------------------------------------

        String responseStudentID =
          getJsonStringValue(response, "student_id");

        String responseDirection =
          getJsonStringValue(response, "direction");

        String responseScanTime =
          getJsonStringValue(response, "scan_time");


        // --------------------------------------------------
        // VALID QR BEEP
        // --------------------------------------------------

        validQRBeep();


        // ==================================================
        // DIRECTION: IN
        // ==================================================

        if (responseDirection == "IN") {

          Serial.println();
          Serial.println("DIRECTION: IN");
          Serial.println("Opening IN servo...");

          // Gate opens immediately after validation.
          openIN();
        }


        // ==================================================
        // DIRECTION: OUT
        // ==================================================

        else if (responseDirection == "OUT") {

          Serial.println();
          Serial.println("DIRECTION: OUT");
          Serial.println("Opening OUT servo...");

          // Gate opens immediately after validation.
          openOUT();
        }


        // ==================================================
        // DIRECTION ERROR
        // ==================================================

        else {

          Serial.println();
          Serial.println("ERROR: DIRECTION NOT FOUND");

          networkErrorBeep();

          http.end();
          return;
        }


        // ==================================================
        // EMAIL NOTIFICATION
        // ==================================================
        //
        // IMPORTANT:
        // This happens AFTER the gate operation.
        // SMTP/email can therefore no longer delay the gate.
        //

        sendNotification(
          responseStudentID,
          responseDirection,
          responseScanTime
        );
      }


      // ====================================================
      // STUDENT NOT FOUND / INVALID QR
      // ====================================================

      else {

        Serial.println();
        Serial.println("======================================");
        Serial.println("         ACCESS DENIED");
        Serial.println("         STUDENT NOT FOUND");
        Serial.println("======================================");

        invalidQRBeep();
      }
    }


    // ======================================================
    // OTHER HTTP ERROR
    // ======================================================

    else {

      Serial.println();
      Serial.println("SERVER RETURNED AN ERROR");

      networkErrorBeep();
    }
  }


  // ========================================================
  // HTTP CONNECTION FAILED
  // ========================================================

  else {

    Serial.println();
    Serial.println("======================================");
    Serial.println("       HTTP REQUEST FAILED");
    Serial.println("======================================");

    Serial.print("ERROR CODE: ");
    Serial.println(httpCode);

    Serial.println();

    Serial.println("Possible causes:");
    Serial.println("1. XAMPP Apache stopped");
    Serial.println("2. Wrong PC IP");
    Serial.println("3. Windows Firewall");
    Serial.println("4. ESP32 not on same Wi-Fi");
    Serial.println("5. PHP API unavailable");

    networkErrorBeep();
  }


  http.end();

  Serial.println("======================================");
}


// ============================================================
//                    READ GM861
// ============================================================

void readGM861() {

  while (GM861.available()) {

    char c = GM861.read();
    lastScannerByteAt = millis();


    // ========================================================
    // END OF QR DATA
    // ========================================================

    if (c == '\n' || c == '\r') {

      if (qrData.length() > 0) {

        String scannedQR = qrData;
        qrData = "";

        // GM861 can send the same frame repeatedly while a QR remains visible.
        // Process that QR once, then wait for the scanner to go quiet.
        if (!scannerReady && scannedQR == lockedQR) {
          Serial.println("DUPLICATE QR FRAME - IGNORED");
          continue;
        }

        lockedQR = scannedQR;
        scannerReady = false;

        sendStudentToServer(scannedQR);

        // Start the release timer after the network request finishes. This
        // prevents buffered/repeated frames from immediately retriggering it.
        lastScannerByteAt = millis();
      }
    }


    // ========================================================
    // STORE QR CHARACTER
    // ========================================================

    else {

      qrData += c;

      // Safety limit

      if (qrData.length() > 100) {

        qrData = "";
      }
    }
  }

  if (
    !scannerReady &&
    qrData.length() == 0 &&
    millis() - lastScannerByteAt >= SCANNER_RELEASE_GAP
  ) {
    scannerReady = true;
    lockedQR = "";
    Serial.println("QR SCANNER READY");
  }
}


// ============================================================
//                    SETUP
// ============================================================

void setup() {

  Serial.begin(115200);

  delay(1000);


  // ========================================================
  // GM861
  // ========================================================

  GM861.begin(
    9600,
    SERIAL_8N1,
    GM861_RX,
    GM861_TX
  );


  // ========================================================
  // SERVO SETUP
  // ========================================================

  servoIN.attach(servoINPin);

  servoOUT.attach(servoOUTPin);

  servoIN.write(CLOSED_ANGLE);

  servoOUT.write(CLOSED_ANGLE);


  // ========================================================
  // BUTTON SETUP
  // ========================================================

  pinMode(buttonINPin, INPUT_PULLUP);

  pinMode(buttonOUTPin, INPUT_PULLUP);


  // ========================================================
  // BUZZER SETUP
  // ========================================================

  pinMode(buzzerPin, OUTPUT);

  digitalWrite(buzzerPin, LOW);


  // ========================================================
  // WIFI
  // ========================================================

  connectWiFi();


  // ========================================================
  // SYSTEM INFORMATION
  // ========================================================

  Serial.println();
  Serial.println("======================================");
  Serial.println("          SMARTGATE");
  Serial.println("======================================");

  Serial.println();

  Serial.println("IN SERVO       -> GPIO 27");
  Serial.println("IN BUTTON      -> GPIO 26");

  Serial.println("OUT SERVO      -> GPIO 25");
  Serial.println("OUT BUTTON     -> GPIO 33");

  Serial.println();

  Serial.println("GM861 RX       -> GPIO 16");
  Serial.println("GM861 TX       -> GPIO 17");
  Serial.println("GM861 BAUD     -> 9600");

  Serial.println();

  Serial.println("ACTIVE BUZZER  -> GPIO 4");

  Serial.println();

  Serial.println("DATABASE       -> fcapstone");
  Serial.println("XAMPP SERVER   -> 192.168.100.101");
  Serial.println("SCAN API       -> /fcapstone/scan.php");
  Serial.println("EMAIL API      -> /fcapstone/send_notification.php");

  Serial.println();

  Serial.println("======================================");
  Serial.println("            SYSTEM READY");
  Serial.println("======================================");
}


// ============================================================
//                    MAIN LOOP
// ============================================================

void loop() {

  // ========================================================
  // READ GM861
  // ========================================================

  readGM861();


  // ========================================================
  // READ BUTTONS
  // ========================================================

  bool inPressed =
    (digitalRead(buttonINPin) == LOW);

  bool outPressed =
    (digitalRead(buttonOUTPin) == LOW);


  // ========================================================
  // IN BUTTON - JUST PRESSED
  // ========================================================

  if (inPressed && !inButtonActive) {

    inButtonActive = true;

    inEmergencyTriggered = false;

    inButtonStartTime = millis();

    Serial.println();
    Serial.println("IN BUTTON PRESSED");
  }


  // ========================================================
  // IN BUTTON - BEING HELD
  // ========================================================

  if (inPressed &&
      inButtonActive &&
      !inEmergencyTriggered) {

    unsigned long heldTime =
      millis() - inButtonStartTime;

    if (heldTime >= LONG_PRESS_TIME) {

      inEmergencyTriggered = true;

      if (!emergencyMode) {

        emergencyOpen();
      }

      else {

        emergencyClose();
      }
    }
  }


  // ========================================================
  // IN BUTTON - RELEASED
  // ========================================================

  if (!inPressed && inButtonActive) {

    unsigned long pressDuration =
      millis() - inButtonStartTime;

    inButtonActive = false;


    // Short press

    if (pressDuration < LONG_PRESS_TIME &&
        !emergencyMode) {

      Serial.println();
      Serial.println("IN BUTTON SHORT PRESS");

      openIN();
    }
  }


  // ========================================================
  // OUT BUTTON - JUST PRESSED
  // ========================================================

  if (outPressed && !outButtonActive) {

    outButtonActive = true;

    outEmergencyTriggered = false;

    outButtonStartTime = millis();

    Serial.println();
    Serial.println("OUT BUTTON PRESSED");
  }


  // ========================================================
  // OUT BUTTON - BEING HELD
  // ========================================================

  if (outPressed &&
      outButtonActive &&
      !outEmergencyTriggered) {

    unsigned long heldTime =
      millis() - outButtonStartTime;

    if (heldTime >= LONG_PRESS_TIME) {

      outEmergencyTriggered = true;

      if (!emergencyMode) {

        emergencyOpen();
      }

      else {

        emergencyClose();
      }
    }
  }


  // ========================================================
  // OUT BUTTON - RELEASED
  // ========================================================

  if (!outPressed && outButtonActive) {

    unsigned long pressDuration =
      millis() - outButtonStartTime;

    outButtonActive = false;


    // Short press

    if (pressDuration < LONG_PRESS_TIME &&
        !emergencyMode) {

      Serial.println();
      Serial.println("OUT BUTTON SHORT PRESS");

      openOUT();
    }
  }


  delay(10);
}
