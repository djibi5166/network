const inputs = document.querySelectorAll(".otp-input");

inputs.forEach((input, index) => {
    // 1. AUTO FOCUS NEXT
    input.addEventListener("input", () => {
        // Optional: Ensure only numbers are entered
        if (index === 4) submitOTP();
        input.value = input.value.replace(/[^0-9]/g, "");

        if (input.value && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
    });

    // 2. BACKSPACE FOCUS PREVIOUS
    input.addEventListener("keydown", e => {
        if (e.key === "Backspace" && !input.value && index > 0) {
            inputs[index - 1].focus();
        }
    });

    // 3. PASTE SUPPORT (Moved INSIDE the loop)
    input.addEventListener("paste", e => {
        e.preventDefault(); // Stop default browser paste

        // Get pasted text
        const pasteData = (e.clipboardData || window.clipboardData).getData(
            "text"
        );

        // Keep only numbers and split into array
        const chars = pasteData.replace(/\D/g, "").split("");

        if (chars.length > 0) {
            // Fill inputs
            inputs.forEach((box, i) => {
                if (chars[i]) {
                    box.value = chars[i];
                } else {
                    box.value = ""; // Clear remaining if pasted code is shorter
                }
            });

            // Focus the last filled box
            const lastIndex = Math.min(chars.length, inputs.length) - 1;
            if (lastIndex >= 0) {
                inputs[lastIndex].focus();
            }

            // Auto-submit if fully filled
            if (chars.length === inputs.length) {
                showToast();
                submitOTP();
            }
        }
    });
});

// Helper function to check current value
function checkOTP() {
    let otp = "";
    inputs.forEach(i => (otp += i.value));
    return otp;
}

function submitOTP() {
    const otp = checkOTP();

    if (otp.length !== inputs.length) {
        showToast("Please enter full code", "error");
        return;
    }

    // Disable inputs while checking
    inputs.forEach(i => (i.disabled = true));

    // 🔁 Simulate API call
    setTimeout(() => {
        fetch(API + "/api/auth/checkOtp.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" }, // <-- Ajout important
            body: JSON.stringify({
                token: otp
            })
        })
            .then(r => r.json())
            .then(d => {
            	//alert(JSON.stringify(d))
                if (d.message) {
                	// Disable inputs while checking
                   inputs.forEach(i => i.disabled = true);
                    dToken('green')
                	el('displayToken').innerText=d.message;
                    showToast("opt valide !",'success');
                } else {
                	dToken('red')
                	let a = '<br><a href="#" onclick="resetOtp()" style="color:blue;"><i class="fas fa-share aria-hiden="true"></i> renvoyer otp ancore ?</a>';
                	
                	el('displayToken').innerHTML=d.error+a;
                	
                	
                    showToast(d.error || "OTP Invalid !", "error");
                    
                    inputs.forEach(i => {
			            i.value = "";
			            i.disabled = false;
			        });
			        
                    inputs[0].focus();
                }
            })
            .catch(e => showToast(e.error || "Erreur réseau", "error"));
        
    }, 500);
}

function dToken(info){
	let clr = info =='red'?'red':'green';
	el('displayToken').style.color=clr;
	let e = el('displayToken');
	e.style.userSelect = 'none';
     e.style.webkitUserSelect = 'none'; // Chrome, Safari
     e.style.msUserSelect = 'none';     // Old Edge
}

function resetOtp(){
    try{
     let username = localStorage.getItem('resetPass');
	
	fetch(API + "/api/auth/forgot_password.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ username })
                })
                    .then(response => {
                        /*if (!response.ok) {
                            throw new Error(`HTTP ${response.error}`);
                        }*/
                       // alert(response.text())
                        return response.json()
                    })
                    .then(data => {
                        if (data.message) {
                            el("displayToken").innerText = data.message;
                            //el("fp_token").value = "";
                            dToken('green')
                            //switchAuth("forgotStep2");
                        } else {
                            showToast(data.error || "Unknown error", "error");
                        }
                    })
                    .catch(err => {
                      //  console.error(err);
                        showToast(err || "Server error. Try again later.", "error");
                    });
    }catch(e){
    	showToast(e,'error')
    }
}

function lStorageRm(token){
	localStorage.removeItem(token)
}