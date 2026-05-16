import Plib from "/coaltheme/js/pickle.js";

export default class Page {
  constructor() {
    this.plib = new Plib();
    this.pageEvents();
    this.countDown();
  }

  countDown() {
    const target = document.getElementById('countdown');

    let i = 0;
    const limit = 120;
    target.innerHTML = limit;
    const intr = setInterval(() => {
      i++;
      target.innerHTML = limit - i;

      if (i == limit) {
        clearInterval(intr);
        document.querySelectorAll('.send-code').forEach(el => el.value = '*');
        Swal.fire({
          title: "Uyarı !",
          text: "Gönderilen kod geçerliliğini yitirmiştir.",
          icon: "error",
          confirmButtonText: 'Yeniden Giriş Yap',
          willClose: () => {
            document.getElementById('login-form').submit();
          }
        });
      }

    }, 1000);
  }

  pageEvents() {

    //listen code steps
    const inps = document.querySelectorAll('.send-code');
    inps.forEach(el =>{
      el.addEventListener('keydown' , e => {
        if (e.key === 'Backspace') {
          const step = parseInt(e.target.dataset.step);
          const prev = document.querySelector('.send-code[data-step="' + (step - 1) + '"]');

          if (prev !== null) prev.focus();
        }
      });

      el.addEventListener('input', e => {
        if (e.target.value.trim() === '') return false;




        const step = parseInt(e.target.dataset.step);
        const next = document.querySelector('.send-code[data-step="' + (step + 1) + '"]');

        if (next !== null) next.focus();
      });

      el.addEventListener('paste', (event) => {
        // Prevent the default paste action if needed (optional)
        event.preventDefault();

        // Get the pasted text data from the clipboard
        const pastedData = event.clipboardData.getData('text');
        pastedData.trim().split('').forEach((char, index) => {
          const targetInput = document.querySelector('input[name="code_' + (index + 1) + '"]');
          if (targetInput && !isNaN(char)) {
            targetInput.value = char;
          }
        });
      });

    });

    document.getElementById('btn-next').addEventListener('click', e => {
      e.target.disabled = true;
      e.target.querySelector('.indicator-progress').style.display = 'unset';

      const checkForm = this.plib.checkForm('.send-code');
      if (!checkForm.valid) {
        e.target.disabled = false;
        e.target.querySelector('.indicator-progress').style.display = 'none';
      } else {
        document.getElementById('login-form').submit();
      }
    });
  }
  
}
