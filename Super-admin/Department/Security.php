<!-- Universal Security Permission Modal -->
<div id="universalPermissionModal"
    style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); z-index:100001; justify-content:center; align-items:center; backdrop-filter:blur(15px); -webkit-backdrop-filter:blur(15px);">
    <div
        style="background:#fff; width:90%; max-width:420px; border-radius:24px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; animation: slideDown 0.3s ease-out; border: 1px solid rgba(255,255,255,0.5);">

        <!-- Header -->
        <div id="univ-modal-header"
            style="background:#f59e0b; padding:30px 20px 20px; text-align:center; position:relative;">
            <div
                style="width:70px; height:70px; background:rgba(255,255,255,0.25); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <i id="univ-modal-icon" class="fas fa-lock" style="color:white; font-size:32px;"></i>
            </div>
            <h2 style="color:white; margin:0; font-size:22px; font-weight:800; letter-spacing: -0.5px;">Security Check
            </h2>
            <div
                style="position: absolute; bottom: -10px; left: 0; width: 100%; height: 20px; background: #fff; border-radius: 20px 20px 0 0;">
            </div>
        </div>

        <!-- Body -->
        <div style="padding:10px 30px 30px; text-align:center;">
            <p style="color:#64748b; margin-bottom:20px; font-size:15px; line-height:1.5;">
                Enter your security PIN to access <strong id="univ-modal-module-name">Module</strong> with Super Admin
                privileges.
            </p>

            <div style="margin-bottom: 25px; position: relative;">
                <div id="univ-pin-container"
                    style="display: flex; gap: 12px; justify-content: center; margin-bottom: 10px;">
                    <input type="password" class="univ-pin-box" maxlength="1" inputmode="numeric"
                        style="width: 50px; height: 60px; font-size: 24px; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #0f172a; outline: none; transition: all 0.2s;">
                    <input type="password" class="univ-pin-box" maxlength="1" inputmode="numeric"
                        style="width: 50px; height: 60px; font-size: 24px; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #0f172a; outline: none; transition: all 0.2s;">
                    <input type="password" class="univ-pin-box" maxlength="1" inputmode="numeric"
                        style="width: 50px; height: 60px; font-size: 24px; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #0f172a; outline: none; transition: all 0.2s;">
                    <input type="password" class="univ-pin-box" maxlength="1" inputmode="numeric"
                        style="width: 50px; height: 60px; font-size: 24px; text-align: center; border: 2px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #0f172a; outline: none; transition: all 0.2s;">
                </div>
                <span id="univ-pin-error"
                    style="color: #ef4444; font-size: 13px; font-weight: 600; display: none;">Incorrect PIN</span>
            </div>

            <div style="display:flex; gap:12px; justify-content:center;">
                <button id="cancelUniv"
                    style="padding:12px 24px; border:1px solid #cbd5e1; background:white; color:#64748b; border-radius:12px; cursor:pointer; font-weight:600; transition:all 0.2s; flex: 1;">
                    Cancel
                </button>
                <button id="confirmUniv"
                    style="padding:12px 24px; border:none; background: linear-gradient(135deg, #f59e0b, #d97706); color:white; border-radius:12px; cursor:pointer; font-weight:600; box-shadow:0 4px 12px rgba(245, 158, 11, 0.2); transition:all 0.2s; flex: 1;">
                    Verify & Access
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Universal Security Permission Function
    function openUniversalPermission(element, event) {
        if (event) event.preventDefault();
        const modal = document.getElementById('universalPermissionModal');
        const targetUrl = element.getAttribute('data-target-url');
        const moduleName = element.getAttribute('data-name');
        const moduleIcon = element.getAttribute('data-icon');
        const moduleColor = element.getAttribute('data-color');

        const requiredPin = element.getAttribute('data-pin') || '1234';

        const header = document.getElementById('univ-modal-header');
        const icon = document.getElementById('univ-modal-icon');
        const nameSpan = document.getElementById('univ-modal-module-name');
        const confirmBtn = document.getElementById('confirmUniv');

        // Set Dynamic Content
        header.style.background = moduleColor;
        confirmBtn.style.background = moduleColor;
        confirmBtn.style.boxShadow = `0 4px 12px 0 ${moduleColor}40`; // 25% opacity

        icon.className = moduleIcon;
        nameSpan.textContent = moduleName;

        const inputs = document.querySelectorAll('.univ-pin-box');
        const pinError = document.getElementById('univ-pin-error');

        // Reset state
        inputs.forEach(input => {
            input.value = '';
            input.style.borderColor = '#e2e8f0';
            input.style.backgroundColor = '#fff';
        });
        pinError.style.display = 'none';
        modal.style.display = 'flex';

        // Focus on first input
        setTimeout(() => inputs[0].focus(), 100);

        // Handle Input Logic (Auto-focus, Backspace, Paste)
        inputs.forEach((input, index) => {
            input.onkeydown = (e) => {
                // Backspace: move to prev
                if (e.key === 'Backspace' && !e.target.value) {
                    if (index > 0) inputs[index - 1].focus();
                }
                // Enter on last input
                if (e.key === 'Enter' && index === 3) {
                    verifyAndProceedUniv();
                }
            };

            input.oninput = (e) => {
                const val = e.target.value;
                // Auto move next
                if (val.length === 1 && index < 3) {
                    inputs[index + 1].focus();
                }
                // Clear error on type
                pinError.style.display = 'none';
                inputs.forEach(i => i.style.borderColor = '#e2e8f0');
            };

            // Paste support
            input.onpaste = (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').slice(0, 4).split('');
                if (pasteData.length > 0) {
                    pasteData.forEach((char, i) => {
                        if (inputs[i]) inputs[i].value = char;
                    });
                    // Focus last filled or next empty
                    const target = Math.min(pasteData.length, 3);
                    inputs[target].focus();
                }
            };
        });

        // Setup buttons
        const cancelBtn = document.getElementById('cancelUniv');

        // Clone to remove old listeners
        const newConfirm = confirmBtn.cloneNode(true);
        const newCancel = cancelBtn.cloneNode(true);

        confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
        cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);

        // Verify PIN function
        function verifyAndProceedUniv() {
            let pin = '';
            inputs.forEach(i => pin += i.value);

            if (pin === requiredPin) {
                inputs.forEach(i => {
                    i.style.borderColor = '#10b981';
                    i.style.backgroundColor = '#ecfdf5';
                });
                newConfirm.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Verifying...';
                newConfirm.disabled = true;
                setTimeout(() => {
                    window.location.href = targetUrl;
                }, 800);
            } else {
                inputs.forEach(i => {
                    i.style.borderColor = '#ef4444';
                    i.style.backgroundColor = '#fef2f2';
                });
                pinError.style.display = 'block';

                // Shake animation effect for container
                const container = document.getElementById('univ-pin-container');
                container.animate([
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-10px)' },
                    { transform: 'translateX(10px)' },
                    { transform: 'translateX(0)' }
                ], {
                    duration: 300,
                    iterations: 1
                });

                // Clear inputs
                inputs.forEach(i => i.value = '');
                inputs[0].focus();
            }
        }

        newConfirm.addEventListener('click', verifyAndProceedUniv);

        newCancel.addEventListener('click', function () {
            modal.style.display = 'none';
        });

        // Close on outside click
        modal.onclick = function (e) {
            if (e.target === modal) modal.style.display = 'none';
        }
    }
</script>