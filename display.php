<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>SmartGate Display</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family: Arial, Helvetica, sans-serif;

            background: #f4f8fc;

            color: #1e293b;

        }


        /* =====================================================
           WELCOME SCREEN
        ===================================================== */

        #welcomeScreen {

            min-height: 100vh;

            display: flex;

            flex-direction: column;

            justify-content: center;

            align-items: center;

            text-align: center;

        }


        .logo {

            width: 250px;

            height: 250px;

            border-radius: 50%;

            background: #0d47a1;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 38px;

            font-weight: bold;

            margin-bottom: 30px;

        }


        .welcomeTitle {

            font-size: 70px;

            font-weight: 800;

            color: #0d47a1;

            margin-bottom: 15px;

        }


        .welcomeSubtitle {

            font-size: 40px;

            color: #64748b;

        }


        .scanIcon {

            font-size: 65px;

            margin-top: 35px;

        }


        /* =====================================================
           STUDENT SCREEN
        ===================================================== */

        #studentScreen {
            display: none;
            min-height: 100vh;
            padding: 32px 24px;
            background: linear-gradient(135deg, #eef5ff 0%, #f8fbff 100%);
        }


        .header {

            text-align: center;

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 38px;

            color: #0d47a1;

        }


        .header p {

            margin-top: 5px;

            font-size: 18px;

            color: #64748b;

        }


        .studentCard {
            width: min(1120px, 96%);
            max-width: 1120px;
            min-height: 0;
            margin: 20px auto 0;
            background: #ffffff;
            border: 1px solid #dbe7f5;
            border-radius: 22px;
            padding: 28px 32px 32px;
            box-shadow: 0 12px 35px rgba(13, 71, 161, 0.12);
            display: block;
        }

        .studentCardLogo {
            width: 88px;
            height: 88px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .studentCardLogo img {
            width: 88px;
            height: 88px;
            object-fit: contain;
            display: block;
        }

        .studentCardContent {
            width: 100%;
            max-width: 1050px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 35px;
        }


        /* =====================================================
           PHOTO
        ===================================================== */

        .photoContainer {
            width: 285px;
            min-width: 285px;
            height: 350px;
            border-radius: 18px;
            overflow: hidden;
            background: #edf3f9;
            border: 1px solid #dbe5f0;
            display: flex;
            align-items: center;
            justify-content: center;
        }


        #studentPhoto {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: none;

        }


        #noPhoto {

            color: #64748b;

            font-size: 25px;

            font-weight: bold;

        }


        /* =====================================================
           STUDENT INFORMATION
        ===================================================== */

        .studentInfo {
            flex: 1;
            min-width: 0;
            text-align: center;
        }


        #studentName {
            font-size: 40px;
            line-height: 1.15;
            color: #062b63 !important;
            font-weight: 800;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e7eef7;
            text-align: center;
        }


        .infoRow {
            margin-bottom: 12px;
            padding: 11px 14px;
            background: #f8fbff;
            border: 1px solid #e2ebf5;
            border-radius: 10px;
            text-align: center;
        }


        .label {
            font-size: 11px;
            color: #63758a !important;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }


        .value {
            font-size: 20px;
            line-height: 1.3;
            color: #162f4a !important;
            font-weight: 700;
            overflow-wrap: anywhere;
        }


        /* =====================================================
           ACCESS
        ===================================================== */

        .accessBox {
            margin-top: 15px;
            padding: 15px 20px;
            border-radius: 12px;
            text-align: center;
            background: #ecfdf5;
            border: 2px solid #36a269;
        }


        #accessText {
            font-size: 27px;
            font-weight: 800;
            color: #126b48 !important;
        }


        #directionText {

            font-size: 24px;

            margin-top: 5px;

            font-weight: bold;

            color: #374151;

        }


        .scanTime {

            text-align: center;

            margin-top: 20px;

            font-size: 16px;

            color: #64748b;

        }


        /* =====================================================
   INVALID SCREEN
===================================================== */

#invalidScreen {
    display: none;
    min-height: 100vh;
    width: 100%;
    padding: 30px 20px;

    flex-direction: column;
    justify-content: center;
    align-items: center;

    text-align: center;

    background: #f8f9fa;

    position: relative;
    overflow: hidden;
}


/* =====================================================
   WHITE CARD
===================================================== */

#invalidScreen::before {
    content: "";

    position: absolute;

    width: min(850px, 94%);
    height: 430px;

    background: #ffffff;

    border-radius: 22px;

    border: 1px solid #e5e7eb;

    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);

    z-index: 0;
}


/* Put invalid content above the white card */

#invalidScreen .invalidIcon,
#invalidScreen .invalidTitle,
#invalidScreen .invalidMessage {
    position: relative;
    z-index: 1;
}


/* =====================================================
   RED X ICON
===================================================== */

.invalidIcon {
    width: 105px;
    height: 105px;

    border-radius: 50%;
    background: #dc3545;
    border: 8px solid #ffe2e5;

    color: #ffffff;

    display: flex;
    align-items: center;
    justify-content: center;

    /* Center the X exactly */
    text-align: center;
    line-height: 1;

    font-size: 58px;
    font-weight: bold;

    margin: 0 auto 22px;

    box-shadow: 0 8px 24px rgba(220, 53, 69, 0.18);
}


/* =====================================================
   ACCESS DENIED
===================================================== */

.invalidTitle {
    font-size: 43px;
    line-height: 1.1;

    font-weight: 800;

    color: #b42333 !important;

    text-align: center;

    margin: 0;
}


/* =====================================================
   INVALID MESSAGE
===================================================== */

.invalidMessage {
    max-width: 700px;

    margin-top: 12px;

    padding: 14px 22px;

    border-radius: 10px;

    background: #ffffff;

    border: 1px solid #f3c4c9;

    font-size: 18px;
    line-height: 1.4;

    color: #7f1d1d !important;

    font-weight: 600;

    box-shadow: 0 6px 20px rgba(127, 29, 29, 0.06);
}



        /* =====================================================
           BYPASS SCREEN - ENHANCED
        ===================================================== */
        #bypassScreen { display:none; min-height:100vh; padding:30px 20px; align-items:center; justify-content:center; text-align:center; background:linear-gradient(135deg,#eef5ff 0%,#f8fbff 100%); }
        #bypassScreen .bypassCard { width:min(1200px,95%); max-width:1200px; background:#fff; border:1px solid #dbe7f5; border-radius:22px; padding:40px; box-shadow:0 12px 35px rgba(13,71,161,.12); margin:0 auto; }
        #bypassScreen .bypassIcon { width:82px;height:82px;margin:0 auto 16px;border-radius:50%;background:#0d47a1;border:6px solid #dcecff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:bold; }
        #bypassScreen .bypassTitle { font-size:34px;font-weight:800;color:#083b82 !important;margin-bottom:30px;text-align:center; }
        #studentBypassContent { display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:center !important; gap:55px !important; width:100% !important; max-width:1050px !important; margin:0 auto !important; text-align:left !important; }
        #studentBypassContent .bypassPhotoContainer { flex:0 0 300px !important; width:300px !important; min-width:300px !important; height:370px !important; margin:0 !important; border-radius:18px;overflow:hidden;background:#e8eef5;border:2px solid #dbe7f5;display:flex !important;align-items:center;justify-content:center; }
        #studentBypassContent #bypassPhoto { width:100% !important;height:100% !important;object-fit:cover;display:none; }
        #studentBypassContent #bypassNoPhoto { color:#64748b;font-size:18px;font-weight:800; }
        #studentBypassContent .bypassStudentDetails { flex:1 1 auto !important;width:auto !important;max-width:650px !important;min-width:0 !important;text-align:left !important; }
        #studentBypassContent .bypassVisitorType { font-size:16px;font-weight:800;letter-spacing:1.5px;color:#64748b !important;margin-bottom:15px;text-align:left !important; }
        #studentBypassContent #bypassName { display:block !important;font-size:40px !important;line-height:1.15;font-weight:800;color:#062b63 !important;margin-bottom:20px;text-align:left !important; }
        #studentBypassContent .bypassInfo { display:block !important;width:100% !important;box-sizing:border-box;font-size:17px;color:#243b53 !important;font-weight:600;margin:9px 0;padding:11px 14px;background:#f8fbff;border:1px solid #e2ebf5;border-radius:9px;text-align:left !important; }
        #studentBypassContent .bypassAccessBox { width:100%;box-sizing:border-box;margin:20px 0 12px;padding:15px 20px;border-radius:12px;text-align:center !important;background:#ecfdf5;border:2px solid #36a269; }
        #studentBypassContent #bypassAccessText { font-size:27px;font-weight:800;color:#126b48 !important; }
        #studentBypassContent #bypassReason { margin-top:13px;font-size:15px;color:#63758a !important;font-weight:600;text-align:left !important;background:transparent !important;border:0 !important;padding:0 !important; }
        #studentBypassContent #bypassTime { margin-top:9px;font-size:12px;color:#8a9aae !important;text-align:left !important; }
        #visitorBypassContent { display:none;flex-direction:row;align-items:center;justify-content:center;gap:70px;width:100%;max-width:900px;margin:0 auto; }
        #visitorBypassContent .visitorBypassNameBox,#visitorBypassContent .visitorReasonBox { flex:1;min-width:0;text-align:center; }
        #visitorBypassContent .bypassVisitorType { font-size:16px;font-weight:800;letter-spacing:1.5px;color:#64748b !important;margin-bottom:15px; }
        #visitorBypassContent .visitorBypassName { font-size:42px;line-height:1.2;font-weight:800;color:#062b63 !important; }
        #visitorBypassContent .visitorReasonLabel { font-size:14px;font-weight:800;letter-spacing:1.5px;color:#64748b !important;margin-bottom:10px; }
        #visitorBypassContent .visitorBypassReason { font-size:24px;line-height:1.4;font-weight:700;color:#243b53 !important;background:#f8fbff;border:1px solid #dbe7f5;border-radius:12px;padding:18px 22px; }

        /* =====================================================
           TABLET
        ===================================================== */

        @media (max-width: 800px) {

            .welcomeTitle {

                font-size: 40px;

            }


            .welcomeSubtitle {

                font-size: 20px;

            }


            .studentCard {
                padding: 22px 18px 26px;
            }

            .studentCardLogo {
                width: 72px;
                height: 72px;
                margin-bottom: 16px;
            }

            .studentCardLogo img {
                width: 72px;
                height: 72px;
            }

            .studentCardContent {
                flex-direction: column;
                gap: 22px;
                text-align: center;
            }


            .photoContainer {
                width: 250px;
                min-width: 250px;
                height: 300px;
            }


            #studentName {
                font-size: 32px;
                text-align: center;
            }


            .studentInfo {
                width: 100%;
                max-width: 760px;
                margin: 0 auto;
                text-align: center;
            }


            .value {
                font-size: 19px;
            }

        }

    
        @media (max-width:1100px) {
            #bypassScreen .bypassCard { width:94%; padding:25px 20px; }
            #studentBypassContent { flex-direction:column !important; gap:25px !important; text-align:center !important; }
            #studentBypassContent .bypassPhotoContainer { flex:0 0 300px !important; width:250px !important; min-width:250px !important; height:300px !important; margin:0 auto !important; }
            #studentBypassContent .bypassStudentDetails { width:100% !important; max-width:100% !important; text-align:center !important; }
            #studentBypassContent .bypassVisitorType,#studentBypassContent #bypassName,#studentBypassContent .bypassInfo,#studentBypassContent #bypassReason,#studentBypassContent #bypassTime { text-align:center !important; }
            #studentBypassContent #bypassName { font-size:32px !important; }
            #visitorBypassContent { flex-direction:column !important; gap:25px !important; text-align:center !important; }
            #visitorBypassContent .visitorBypassNameBox,#visitorBypassContent .visitorReasonBox { width:100%; flex:none; }
            #visitorBypassContent .visitorBypassName { font-size:34px; }
            #visitorBypassContent .visitorBypassReason { font-size:21px; }
        }

        /* =====================================================
           FINAL BYPASS MODE LOCK
           The mode is controlled from #bypassScreen itself.
           This intentionally uses very high specificity so an
           older smartgate_theme.css rule cannot bring the student
           panel back while a visitor is being displayed.
        ===================================================== */
        html body #bypassScreen[data-bypass-mode="visitor"] #studentBypassContent {
            display:none !important;
            visibility:hidden !important;
            position:absolute !important;
            width:0 !important;
            height:0 !important;
            max-width:0 !important;
            min-width:0 !important;
            overflow:hidden !important;
            opacity:0 !important;
            pointer-events:none !important;
            margin:0 !important;
            padding:0 !important;
        }
        html body #bypassScreen[data-bypass-mode="visitor"] #visitorBypassContent {
            display:flex !important;
            visibility:visible !important;
            position:relative !important;
            width:100% !important;
            height:auto !important;
            opacity:1 !important;
            pointer-events:auto !important;
        }
        html body #bypassScreen[data-bypass-mode="student"] #studentBypassContent {
            display:flex !important;
            visibility:visible !important;
            position:relative !important;
            width:100% !important;
            height:auto !important;
            opacity:1 !important;
            pointer-events:auto !important;
        }
        html body #bypassScreen[data-bypass-mode="student"] #visitorBypassContent {
            display:none !important;
            visibility:hidden !important;
            position:absolute !important;
            width:0 !important;
            height:0 !important;
            max-width:0 !important;
            min-width:0 !important;
            overflow:hidden !important;
            opacity:0 !important;
            pointer-events:none !important;
            margin:0 !important;
            padding:0 !important;
        }


        /* =====================================================
           BYPASS RENDER AREA — ONE MODE ONLY
           The JavaScript creates either the student layout OR the
           visitor layout. There is never a second bypass panel.
        ===================================================== */
        #bypassRenderArea { width:100%; }
        #bypassRenderArea #studentBypassContent.studentBypassActive {
            display:flex !important;
            flex-direction:row !important;
            align-items:center !important;
            justify-content:center !important;
            gap:55px !important;
            width:100% !important;
            max-width:1050px !important;
            margin:0 auto !important;
        }
        #bypassRenderArea #visitorBypassContent.visitorBypassActive {
            display:flex !important;
            flex-direction:row !important;
            align-items:center !important;
            justify-content:center !important;
            gap:70px !important;
            width:100% !important;
            max-width:900px !important;
            margin:0 auto !important;
        }
        @media (max-width:1100px) {
            #bypassRenderArea #studentBypassContent.studentBypassActive {
                flex-direction:column !important;
                gap:25px !important;
                text-align:center !important;
            }
            #bypassRenderArea #visitorBypassContent.visitorBypassActive {
                flex-direction:column !important;
                gap:25px !important;
                text-align:center !important;
            }
            #bypassRenderArea #visitorBypassContent .visitorBypassNameBox,
            #bypassRenderArea #visitorBypassContent .visitorReasonBox {
                width:100% !important;
                flex:none !important;
            }
        }

        /* =====================================================
           FULLSCREEN BUTTON
        ===================================================== */
        #fullscreenBtn {
            position: fixed;
            top: 14px;
            right: 14px;
            z-index: 999999;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            background: #0d47a1;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(0,0,0,.18);
            display: block;
        }

        #fullscreenBtn.fullscreen-active {
            display: none !important;
        }

        @media (max-width: 800px) {
            #fullscreenBtn {
                top: 8px;
                right: 8px;
                padding: 8px 10px;
                font-size: 11px;
            }
        }

    </style>

<link rel="stylesheet" href="smartgate_theme.css">
</head>


<body>

<button id="fullscreenBtn" type="button" aria-label="Enter fullscreen">
    ⛶ FULL SCREEN
</button>


<!-- ============================================================
     WELCOME SCREEN
============================================================ -->

<div id="welcomeScreen">

    <div class="logo">
        <img src="assets/smartgate-logo.png" alt="SmartGate logo">
    </div>


    <div class="welcomeTitle">
        WELCOME TO SMARTGATE
    </div>


    <div class="welcomeSubtitle">
        Please scan your student QR code
    </div>

</div>



<!-- ============================================================
     STUDENT SCREEN
============================================================ -->

<div id="studentScreen">


    <div class="studentCard">

        <!-- SMARTGATE LOGO -->
        <div class="studentCardLogo">
            <img src="assets/smartgate-logo.png" alt="SmartGate logo">
        </div>

        <div class="studentCardContent">

        <!-- PHOTO -->

        <div class="photoContainer">

            <img
                id="studentPhoto"
                src=""
                alt="Student Photo"
            >


            <div id="noPhoto">
                NO PHOTO
            </div>

        </div>



        <!-- INFORMATION -->

        <div class="studentInfo">


            <div id="studentName">
                Student Name
            </div>



            <div class="infoRow">

                <div class="label">
                    Student ID
                </div>

                <div
                    id="studentID"
                    class="value"
                >
                </div>

            </div>



            <div class="infoRow">

                <div class="label">
                    Program
                </div>

                <div
                    id="program"
                    class="value"
                >
                </div>

            </div>



            <div class="infoRow">

                <div class="label">
                    Year & Section
                </div>

                <div
                    id="yearSection"
                    class="value"
                >
                </div>

            </div>



            <!-- ACCESS -->

            <div class="accessBox">

                <div id="accessText">
                    ACCESS GRANTED
                </div>

                <div id="directionText">
                    IN
                </div>

            </div>



            <div
                id="scanTime"
                class="scanTime"
            >
            </div>


        </div>

        </div> <!-- /.studentCardContent -->

    </div>

</div>




<!-- ============================================================
     BYPASS SCREEN
============================================================ -->
<div id="bypassScreen">
    <div class="bypassCard">
        <div class="bypassIcon">✓</div>
        <div class="bypassTitle">BYPASS ACCESS</div>

        <!-- Only ONE bypass layout is inserted here at a time. -->
        <div id="bypassRenderArea"></div>
    </div>
</div>

<!-- ============================================================
     INVALID SCREEN
============================================================ -->

<div id="invalidScreen">

    <div class="invalidCard">

        <div class="invalidIcon">
            ✕
        </div>

        <div class="invalidTitle">
            ACCESS DENIED
        </div>

        <div
            id="invalidMessage"
            class="invalidMessage"
        >
            INVALID QR CODE
        </div>

    </div>

</div>



<script>


// ============================================================
// VARIABLES
// ============================================================

let lastScanID = null;
let displayTimer = null;
let pollInProgress = false;

// Keep the normal visual display at 3 seconds.
const DISPLAY_SECONDS = 3;



// ============================================================
// SHOW WELCOME
// ============================================================

function showWelcome() {
    clearTimeout(displayTimer);
    document.getElementById("welcomeScreen").style.display = "flex";
    document.getElementById("studentScreen").style.display = "none";
    document.getElementById("invalidScreen").style.display = "none";
    const bypassScreen = document.getElementById("bypassScreen");
    bypassScreen.style.display = "none";
    bypassScreen.removeAttribute("data-bypass-mode");

    const studentContent = document.getElementById("studentBypassContent");
    const visitorContent = document.getElementById("visitorBypassContent");
    if (studentContent) {
        studentContent.classList.add("is-hidden");
        studentContent.classList.remove("student-mode");
        // Inline !important guarantees the old student layout cannot remain visible.
        studentContent.style.setProperty("display", "none", "important");
    }
    if (visitorContent) {
        visitorContent.classList.add("is-hidden");
        visitorContent.classList.remove("visitor-mode");
        visitorContent.style.setProperty("display", "none", "important");
    }

    const photo = document.getElementById("bypassPhoto");
    const noPhoto = document.getElementById("bypassNoPhoto");
    if (photo) { photo.src = ""; photo.style.display = "none"; }
    if (noPhoto) noPhoto.style.display = "none";

    ["bypassVisitorType","bypassName","bypassStudentID","bypassProgram","bypassYearSection","bypassDirection","bypassAccessText","bypassReason","bypassTime","visitorBypassName","visitorBypassReason"].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.innerText = "";
    });
}



// ============================================================
// SHOW STUDENT
// ============================================================

function showStudent(data) {
    clearTimeout(displayTimer);

    document.getElementById("welcomeScreen").style.display = "none";
    document.getElementById("studentScreen").style.display = "none";
    document.getElementById("invalidScreen").style.display = "none";

    const bypassScreen = document.getElementById("bypassScreen");
    const renderArea = document.getElementById("bypassRenderArea");

    /*
     * QR STUDENT DISPLAY
     * Uses the EXACT SAME student layout used by the Student Bypass:
     * photo on the left + student details on the right.
     * Only the title is changed so a normal QR scan does not say BYPASS.
     */
    bypassScreen.style.display = "flex";
    bypassScreen.setAttribute("data-bypass-mode", "student");

    const bypassTitle = bypassScreen.querySelector(".bypassTitle");
    if (bypassTitle) {
        bypassTitle.textContent = "STUDENT ACCESS";
    }

    renderArea.innerHTML = `
        <div id="studentBypassContent" class="studentBypassActive">
            <div id="bypassPhotoContainer" class="bypassPhotoContainer">
                <img id="bypassPhoto" src="" alt="Student Photo">
                <div id="bypassNoPhoto">NO PHOTO</div>
            </div>

            <div class="bypassStudentDetails">
                <div id="bypassVisitorType" class="bypassVisitorType">STUDENT</div>

                <div id="bypassName"></div>

                <div id="bypassStudentID" class="bypassInfo"></div>

                <div id="bypassProgram" class="bypassInfo"></div>

                <div id="bypassYearSection" class="bypassInfo"></div>

                <div id="bypassDirection" class="bypassInfo"></div>

                <div class="bypassAccessBox">
                    <div id="bypassAccessText">ACCESS GRANTED</div>
                </div>

                <div id="bypassReason"></div>

                <div id="bypassTime"></div>
            </div>
        </div>
    `;

    document.getElementById("bypassVisitorType").textContent = "STUDENT";

    const photo = document.getElementById("bypassPhoto");
    const noPhoto = document.getElementById("bypassNoPhoto");

    if (data.photo && String(data.photo).trim() !== "") {
        photo.src = String(data.photo).trim() + "?t=" + Date.now();
        photo.style.display = "block";
        noPhoto.style.display = "none";

        photo.onerror = function() {
            photo.style.display = "none";
            noPhoto.style.display = "block";
        };
    } else {
        photo.style.display = "none";
        noPhoto.style.display = "block";
    }

    document.getElementById("bypassName").textContent =
        data.full_name || "-";

    document.getElementById("bypassStudentID").textContent =
        "Student ID: " + (data.student_id || "-");

    document.getElementById("bypassProgram").textContent =
        "Program: " + (data.program || "-");

    document.getElementById("bypassYearSection").textContent =
        "Year & Section: " +
        (data.year_level || "-") +
        " - " +
        (data.section || "-");

    document.getElementById("bypassDirection").textContent =
        "Direction: " + (data.direction || "-");

    document.getElementById("bypassAccessText").textContent =
        "ACCESS GRANTED";

    // Normal QR scan has no bypass reason.
    document.getElementById("bypassReason").textContent = "";

    document.getElementById("bypassTime").textContent =
        "Scan completed: " +
        (data.scan_time || new Date().toLocaleString());

    // Same 3-second display duration.
    displayTimer = setTimeout(function() {
        showWelcome();
    }, 3000);
}


// ============================================================
// SHOW BYPASS
// ============================================================

function showBypass(data) {
    clearTimeout(displayTimer);

    document.getElementById("welcomeScreen").style.display = "none";
    document.getElementById("studentScreen").style.display = "none";
    document.getElementById("invalidScreen").style.display = "none";

    const bypassScreen = document.getElementById("bypassScreen");
    const renderArea = document.getElementById("bypassRenderArea");

    bypassScreen.style.display = "flex";

    // Always restore the correct title for an actual bypass process.
    // A normal QR scan changes this title to "STUDENT ACCESS".
    const bypassTitle = bypassScreen.querySelector(".bypassTitle");
    if (bypassTitle) {
        bypassTitle.textContent = "BYPASS ACCESS";
    }

    // IMPORTANT: Only one bypass layout exists in the DOM at a time.
    // This prevents the student photo/blank fields from ever appearing
    // on a visitor bypass screen.
    renderArea.innerHTML = "";

    const type = String(data.bypass_type || "").trim().toLowerCase();
    const hasVisitorName = String(data.visitor_name || "").trim() !== "";
    const isVisitor = hasVisitorName || type === "visitor";

    if (isVisitor) {
        bypassScreen.setAttribute("data-bypass-mode", "visitor");

        renderArea.innerHTML = `
            <div id="visitorBypassContent" class="visitorBypassActive">
                <div class="visitorBypassNameBox">
                    <div class="bypassVisitorType">VISITOR / GUEST</div>
                    <div id="visitorBypassName" class="visitorBypassName"></div>
                </div>
                <div class="visitorReasonBox">
                    <div class="visitorReasonLabel">REASON</div>
                    <div id="visitorBypassReason" class="visitorBypassReason"></div>
                </div>
            </div>
        `;

        document.getElementById("visitorBypassName").textContent =
            data.visitor_name || "VISITOR / GUEST";
        document.getElementById("visitorBypassReason").textContent =
            data.reason || "No reason provided";

    } else {
        bypassScreen.setAttribute("data-bypass-mode", "student");

        renderArea.innerHTML = `
            <div id="studentBypassContent" class="studentBypassActive">
                <div id="bypassPhotoContainer" class="bypassPhotoContainer">
                    <img id="bypassPhoto" src="" alt="Student Photo">
                    <div id="bypassNoPhoto">NO PHOTO</div>
                </div>
                <div class="bypassStudentDetails">
                    <div id="bypassVisitorType" class="bypassVisitorType">STUDENT BYPASS</div>
                    <div id="bypassName"></div>
                    <div id="bypassStudentID" class="bypassInfo"></div>
                    <div id="bypassProgram" class="bypassInfo"></div>
                    <div id="bypassYearSection" class="bypassInfo"></div>
                    <div id="bypassDirection" class="bypassInfo"></div>
                    <div class="bypassAccessBox"><div id="bypassAccessText">ACCESS GRANTED</div></div>
                    <div id="bypassReason"></div>
                    <div id="bypassTime"></div>
                </div>
            </div>
        `;

        document.getElementById("bypassVisitorType").textContent = "STUDENT BYPASS";

        const photo = document.getElementById("bypassPhoto");
        const noPhoto = document.getElementById("bypassNoPhoto");

        if (data.photo && String(data.photo).trim() !== "") {
            photo.src = String(data.photo).trim() + "?t=" + Date.now();
            photo.style.display = "block";
            noPhoto.style.display = "none";
            photo.onerror = function() {
                photo.style.display = "none";
                noPhoto.style.display = "block";
            };
        } else {
            photo.style.display = "none";
            noPhoto.style.display = "block";
        }

        document.getElementById("bypassName").textContent = data.full_name || "-";
        document.getElementById("bypassStudentID").textContent =
            "Student ID: " + (data.student_id || "-");
        document.getElementById("bypassProgram").textContent =
            "Program: " + (data.program || "-");
        document.getElementById("bypassYearSection").textContent =
            "Year & Section: " + (data.year_level || "-") + " - " + (data.section || "-");
        document.getElementById("bypassDirection").textContent =
            "Direction: " + (data.direction || "-");
        document.getElementById("bypassAccessText").textContent = "ACCESS GRANTED";
        document.getElementById("bypassReason").textContent =
            "SECURITY BYPASS • Reason: " + (data.reason || "-");
        document.getElementById("bypassTime").textContent =
            "Bypass processed: " + (data.display_time || new Date().toLocaleString());
    }

    const remaining = Number(data.seconds_remaining);
    displayTimer = setTimeout(function() {
        showWelcome();
    }, (Number.isFinite(remaining) && remaining > 0) ? remaining * 1000 : 3000);
}


// ============================================================
// SHOW INVALID
// ============================================================

function showInvalid(data) {

    clearTimeout(displayTimer);


    document.getElementById(
        "welcomeScreen"
    ).style.display = "none";


    document.getElementById(
        "studentScreen"
    ).style.display = "none";


    document.getElementById(
        "invalidScreen"
    ).style.display = "flex";


    document.getElementById(
        "invalidMessage"
    ).innerText =
        data.message ||
        "INVALID QR CODE";



    // Return to welcome using the server's 5-second timer.
    const remaining =
        Number(data.seconds_remaining);

    displayTimer = setTimeout(
        function() {
            showWelcome();
        },
        (Number.isFinite(remaining) && remaining > 0)
            ? remaining * 1000
            : 3000
    );}



// ============================================================
// CHECK SERVER
// ============================================================

function checkForScan() {

    // Prevent overlapping HTTP requests if the server is briefly slow.
    if (pollInProgress) return;
    pollInProgress = true;

    fetch(
        "display_data.php?t=" + Date.now(),
        {
            cache: "no-store"
        }
    )
    .then(function(response) {

        if (!response.ok) {
            throw new Error("HTTP " + response.status);
        }

        return response.json();

    })
    .then(function(data) {

        console.log("SmartGate Display Data:", data);

        // ----------------------------------------------------
        // NO ACTIVE / EXPIRED DISPLAY
        // ----------------------------------------------------
        if (data.display !== true) {
            showWelcome();
            return;
        }

        // ----------------------------------------------------
        // IGNORE THE SAME SCAN EVENT
        // ----------------------------------------------------
        if (
            data.scan_id &&
            String(data.scan_id) === String(lastScanID)
        ) {
            return;
        }

        if (data.scan_id) {
            lastScanID = data.scan_id;
        }

        // ----------------------------------------------------
        // BYPASS
        // ----------------------------------------------------
        if (data.scan_type === "bypass") {
            showBypass(data);
            return;
        }

        // ----------------------------------------------------
        // INVALID QR
        // ----------------------------------------------------
        if (
            data.access_denied === true ||
            data.scan_type === "invalid"
        ) {
            showInvalid(data);
            return;
        }

        // ----------------------------------------------------
        // VALID STUDENT
        // ----------------------------------------------------
        if (
            data.success === true &&
            data.scan_type === "valid"
        ) {
            showStudent(data);
            return;
        }

        showWelcome();

    })
    .catch(function(error) {

        console.error(
            "SmartGate Display Error:",
            error
        );

    })
    .finally(function() {
        pollInProgress = false;
    });

}


// ============================================================
// INITIAL SCREEN
// ============================================================

showWelcome();



// ============================================================
// CHECK EVERY 500 MILLISECONDS
// ============================================================

setInterval(
    checkForScan,
    500
);



// ============================================================
// INITIAL CHECK
// ============================================================

checkForScan();



// ============================================================
// FULLSCREEN MODE
// ============================================================

const fullscreenBtn = document.getElementById("fullscreenBtn");

function isFullscreen() {
    return !!(
        document.fullscreenElement ||
        document.webkitFullscreenElement ||
        document.msFullscreenElement
    );
}

function updateFullscreenButton() {
    if (!fullscreenBtn) return;

    if (isFullscreen()) {
        fullscreenBtn.classList.add("fullscreen-active");
    } else {
        fullscreenBtn.classList.remove("fullscreen-active");
    }
}

async function enterSmartGateFullscreen() {
    try {
        const element = document.documentElement;

        if (element.requestFullscreen) {
            await element.requestFullscreen();
        } else if (element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
        } else if (element.msRequestFullscreen) {
            element.msRequestFullscreen();
        } else {
            alert("Fullscreen mode is not supported by this browser.");
            return;
        }

        // fullscreenchange will hide the button.
        updateFullscreenButton();
    } catch (error) {
        console.error("Fullscreen request failed:", error);
        alert("Chrome blocked fullscreen. Tap FULL SCREEN again.");
    }
}

if (fullscreenBtn) {
    fullscreenBtn.addEventListener("click", enterSmartGateFullscreen);
}

document.addEventListener("fullscreenchange", updateFullscreenButton);
document.addEventListener("webkitfullscreenchange", updateFullscreenButton);

updateFullscreenButton();

</script>


</body>

</html>
