import Plib from "/coaltheme/js/pickle.js";
export default class Page {
    
    constructor() {
        this.plib = new Plib();
        
        this.pageEvents();
    }

    pageEvents(){
        const form = document.querySelector("#login-form");
        const submitButton = document.querySelector("#submit-button");
        //listen form
        submitButton.addEventListener('click',async e=>{
            e.preventDefault();
           
            /*const data = new FormData(e.target);
            const formData = Object.fromEntries(data.entries());*/
            e.target.innerHTML = 'Lütfen bekleyin... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>';
            
            //here make validation for password and password check
            const password = document.getElementById('password');
            const passwordCheck = document.getElementById('password-check');

            if(password.value !== passwordCheck.value || (this.plib.checkInputs('password',password) === false)){
                document.getElementById('err-password').style.display = 'block';
                submitButton.innerHTML = 'Kayıt Ol';
                return false;
            }else{
                document.getElementById('err-password').style.display = 'none !important';
            }




            form.submit();
            
            /*await this.plib.transaction('login','login',formData).then(rsp=>{
                if(rsp.success){
                    localStorage.setItem('token',rsp.token);
                    
                    window.location.href = '/admin';
                    Swal.close();
                }else{
                    Swal.fire({
                        icon  : 'error',
                        title : 'Kullanıcı Bulunamadı !!'
                    });
                }
            });*/
        });

        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            const formatPhone = (value) => {
                let digits = (value || '').replace(/\D/g, ''); // Remove non-digit characters
                if (digits.startsWith('90')) digits = digits.slice(2); // Remove leading '90'
                if (digits.startsWith('0')) digits = digits.slice(1); // Remove leading '0'

                const a = digits.slice(0, 3); // Area code
                const b = digits.slice(3, 6); // Next 3 digits
                const c = digits.slice(6, 8); // Next 2 digits
                const d = digits.slice(8, 10); // Last 2 digits

                let out = '';
                if (a) {
                    out += '(' + a + ')'; // Add area code in parentheses
                }
                if (b) out += ' ' + b; // Add next 3 digits
                if (c) out += ' ' + c; // Add next 2 digits
                if (d) out += ' ' + d; // Add last 2 digits

                return out; // Return formatted phone number
            };

            phoneInput.addEventListener('input', (e) => {
                const start = phoneInput.selectionStart || 0;
                const oldLen = phoneInput.value.length;
                phoneInput.value = formatPhone(phoneInput.value); // Apply formatting
                const newLen = phoneInput.value.length;
                const diff = newLen - oldLen;
                const newPos = Math.max(0, start + (diff > 0 ? diff : 0)); // Adjust cursor position
                phoneInput.setSelectionRange(newPos, newPos);
            });

            phoneInput.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                phoneInput.value = formatPhone(paste);
            });

            phoneInput.addEventListener('keydown', (e) => {
                // Allow control keys, but prevent non-digit characters
                if (e.key.length === 1 && !/\d/.test(e.key)) {
                    e.preventDefault();
                }
            });
        }

        document.addEventListener("keypress", (event) => {
            // If the user presses the "Enter" key on the keyboard
            if (event.keyCode == 13) {
                // Cancel the default action, if needed
                event.preventDefault();
                // Trigger the button element with a click
                submitButton.click();
            }
        });
    }   
    


}