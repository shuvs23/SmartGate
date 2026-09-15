(function () {
    "use strict";

    if (window.smartGateActivityRefreshStarted) {
        return;
    }

    window.smartGateActivityRefreshStarted = true;

    var endpoint = "smartgate_activity.php?ts=" + Date.now();
    var latestActivity = null;
    var initialized = false;

    function markFormDirty(event) {
        var form = event.target.closest
            ? event.target.closest("form")
            : null;

        if (form) {
            form.setAttribute("data-smartgate-dirty", "true");
        }
    }

    function hasUnsavedWork() {
        return Boolean(
            document.querySelector(
                '.bypass-modal.is-open, form[data-smartgate-dirty="true"]'
            )
        );
    }

    function checkActivity() {
        fetch(endpoint + "&check=" + Date.now(), {
            cache: "no-store",
            credentials: "same-origin"
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Activity check failed");
                }

                return response.json();
            })
            .then(function (data) {
                if (!data || data.success !== true) {
                    return;
                }

                var currentActivity =
                    String(data.scan_id || 0) + ":" +
                    String(data.bypass_id || 0);

                if (!initialized) {
                    latestActivity = currentActivity;
                    initialized = true;
                    return;
                }

                if (currentActivity === latestActivity) {
                    return;
                }

                if (
                    document.visibilityState !== "visible" ||
                    hasUnsavedWork()
                ) {
                    return;
                }

                latestActivity = currentActivity;
                window.location.reload();
            })
            .catch(function () {
                // A temporary network error should not interrupt the page.
            });
    }

    document.addEventListener("input", markFormDirty, true);
    document.addEventListener("change", markFormDirty, true);
    document.addEventListener("visibilitychange", function () {
        if (document.visibilityState === "visible") {
            checkActivity();
        }
    });

    checkActivity();
    window.setInterval(checkActivity, 1000);
}());

