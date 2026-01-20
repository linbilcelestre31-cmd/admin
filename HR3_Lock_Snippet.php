<?php
// PASTE THIS AT THE VERY TOP OF YOUR hr3/timesheet/dashboard.php FILE (After session_start)
// OR include it: include 'lock_screen.php';
?>
<!-- HR3 LOCK SCREEN SNIPPET -->
<style>
    /* Lock Screen Overlay */
    #hr3-lock-screen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        /* Dark tint */
        backdrop-filter: blur(15px);
        /* BLUR EFFECT */
        -webkit-backdrop-filter: blur(15px);
        z-index: 999999;
        /* Always on top */
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        animation: fadeInLock 0.5s forwards;
    }

    @keyframes fadeInLock {
        to {
            opacity: 1;
        }
    }

    /* Lock Card */
    .lock-card {
        background: #fff;
        width: 90%;
        max-width: 400px;
        border-radius: 24px;
        /* Rounded corners */
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        overflow: hidden;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.5);
        font-family: 'Segoe UI', sans-serif;
    }

    .lock-header {
        background: #f59e0b;
        /* HR3 Color */
        padding: 30px 20px 20px;
    }

    .lock-icon {
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 32px;
        color: white;
    }

    .lock-body {
        padding: 30px;
    }

    /* PIN Input */
    .pin-input {
        width: 100%;
        font-size: 24px;
        letter-spacing: 12px;
        /* Spaced dots */
        text-align: center;
        padding: 15px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        outline: none;
        transition: all 0.3s;
        color: #0f172a;
        margin-bottom: 20px;
    }

    .pin-input:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
    }

    .pin-btn {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .pin-btn:active {
        transform: scale(0.98);
    }

    /* Shake Animation for Error */
    .shake {
        animation: shake 0.4s cubic-bezier(.36, .07, .19, .97) both;
    }

    @keyframes shake {

        10%,
        90% {
            transform: translate3d(-1px, 0, 0);
        }

        20%,
        80% {
            transform: translate3d(2px, 0, 0);
        }

        30%,
        50%,
        70% {
            transform: translate3d(-4px, 0, 0);
        }

        40%,
        60% {
            transform: translate3d(4px, 0, 0);
        }
    }
</style>

<!-- HTML Structure -->
<div id="hr3-lock-screen">
    <div class="lock-card">
        <div class="lock-header">
            <div class="lock-icon">
                <i class="fas fa-lock"></i> <!-- Ensure FontAwesome is loaded -->
            </div>
            <h2 style="color: white; margin: 0; font-weight: 700;">HR3 Locked</h2>
        </div>
        <div class="lock-body">
            <p style="color: #64748b; margin-bottom: 20px;">Enter security PIN to access Dashboard.</p>

            <input type="password" id="sys-pin" class="pin-input" maxlength="4" placeholder="••••" autofocus>
            <div id="pin-msg"
                style="color: #ef4444; font-size: 13px; font-weight: 600; min-height: 20px; margin-bottom: 10px;"></div>

            <button class="pin-btn" onclick="checkPin()">Unlock System</button>
        </div>
    </div>
</div>

<script>
    // AUTO-FOCUS
    document.getElementById('sys-pin').focus();

    // PIN CHECKER
    function checkPin() {
        const pin = document.getElementById('sys-pin');
        const msg = document.getElementById('pin-msg');
        // SET YOUR PIN HERE
        const CORRECT_PIN = "1234";

        if (pin.value === CORRECT_PIN) {
            // Success: Fade out lock screen
            pin.style.borderColor = "#10b981";
            msg.style.color = "#10b981";
            msg.innerText = "Access Granted";
            document.querySelector('.pin-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Unlocking...';

            setTimeout(() => {
                document.getElementById('hr3-lock-screen').style.transition = "opacity 0.5s";
                document.getElementById('hr3-lock-screen').style.opacity = "0";
                setTimeout(() => {
                    document.getElementById('hr3-lock-screen').style.display = "none";
                }, 500);
            }, 500);

            // Optional: Save session state so it doesn't lock again on refresh
            sessionStorage.setItem('hr3_unlocked', 'true');

        } else {
            // Error
            pin.style.borderColor = "#ef4444";
            msg.innerText = "Incorrect PIN";
            pin.value = "";
            pin.classList.add('shake');
            setTimeout(() => pin.classList.remove('shake'), 400);
            pin.focus();
        }
    }

    // CHECK IF ALREADY UNLOCKED (Optional)
    if (sessionStorage.getItem('hr3_unlocked') === 'true') {
        document.getElementById('hr3-lock-screen').style.display = "none";
    }

    // ENTER KEY LOGIC
    document.getElementById('sys-pin').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            checkPin();
        }
    });
</script>