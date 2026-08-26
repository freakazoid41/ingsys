import Plib from "/coaltheme/js/pickle.js";

export default class Page {
  constructor() {
    this.plib = new Plib();
    this.pageEvents();
    this.countDown();
  }

  countDown() {
    const target = document.getElementById('countdown');

    this.countdownLimit = 120;
    this.elapsedSeconds = 0;
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
    }
    target.innerHTML = this.countdownLimit;

    this.countdownInterval = setInterval(() => {
      this.elapsedSeconds++;
      target.innerHTML = this.countdownLimit - this.elapsedSeconds;

      if (this.elapsedSeconds >= this.countdownLimit) {
        clearInterval(this.countdownInterval);
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

  resetCountDown() {
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
    }
    this.countDown();
  }

  setResendCooldown(seconds) {
    const resendButton = document.getElementById('btn-send-code');
    if (!resendButton) {
      return;
    }

    let remaining = Math.max(0, Math.floor(seconds));
    resendButton.disabled = true;
    resendButton.textContent = `Tekrar Gönder (${remaining}s)`;

    if (this.resendInterval) {
      clearInterval(this.resendInterval);
    }

    this.resendInterval = setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        clearInterval(this.resendInterval);
        resendButton.disabled = false;
        resendButton.textContent = 'Tekrar Gönder';
      } else {
        resendButton.textContent = `Tekrar Gönder (${remaining}s)`;
      }
    }, 1000);
  }

  pageEvents() {

    //listen code steps
    const inps = document.querySelectorAll('.send-code');
    inps.forEach(el =>{
      el.addEventListener('keydown' , e => {
        if (e.key === 'Backspace') {
          e.preventDefault();
          const step = parseInt(e.target.dataset.step);
          const prev = document.querySelector('.send-code[data-step="' + (step - 1) + '"]');

          if (e.target.value === '' && prev !== null) {
            prev.value = '';
            prev.focus();
          } else {
            e.target.value = '';
          }
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

    const resendButton = document.getElementById('btn-send-code');
    if (resendButton) {
      resendButton.addEventListener('click', async () => {
        resendButton.disabled = true;
        resendButton.textContent = 'Gönderiliyor...';
        const csrfToken = document.querySelector('input[name="_token"]')?.value;

        try {
          const response = await fetch('/api/auth/resend-code', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({}),
          });

          const payload = await response.json();
          if (payload.success) {
            Swal.mixin({
              toast: true,
              position: "top-end",
              showConfirmButton: false,
              timer: 3000,
              timerProgressBar: true,
              didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
              }
            }).fire({
              icon: "success",
              title: payload.message || 'Kod yeniden gönderildi.'
            });
            console.log('Başarılı', payload.message || 'Kod yeniden gönderildi.', 'success');
            this.resetCountDown();
            this.setResendCooldown(payload.retry_after || 60);
          } else {
            Swal.mixin({
              toast: true,
              position: "top-end",
              showConfirmButton: false,
              timer: 3000,
              timerProgressBar: true,
              didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
              }
            }).fire({
              icon: "error",
              title: payload.message || 'Kod yeniden gönderilemedi.'
            });
            console.log('Hata', payload.message || 'Kod yeniden gönderilemedi.', 'error');
            if (payload.retry_after) {
              this.setResendCooldown(payload.retry_after);
            } else {
              resendButton.disabled = false;
              resendButton.textContent = 'Tekrar Gönder';
            }
          }
        } catch (err) {
          Swal.mixin({
            toast: true,
            position: "top-end",
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
              toast.onmouseenter = Swal.stopTimer;
              toast.onmouseleave = Swal.resumeTimer;
            }
          }).fire({
            icon: "error",
            title: err.message || 'İşlem sırasında hata oluştu.'
          });
          console.log('Hata', err.message || 'İşlem sırasında hata oluştu.', 'error');
          resendButton.disabled = false;
          resendButton.textContent = 'Tekrar Gönder';
        }
      });
    }
  }
  
}
