window.VictoryFormHandler = function(params) {
    this.form = document.getElementById(params.formId);
    if (!this.form) {
        console.error(`Form with id "${params.formId}" not found.`);
        return;
    }

    this.submitBtn = this.form.querySelector('button[type="submit"]');
    this.errorsDiv = document.getElementById(params.formId + '_errors');
    this.useSmartCaptcha = params.useSmartCaptcha || false;
    this.smartCaptchaId = null;

    this.init();
};

VictoryFormHandler.prototype.init = function() {
    this.form.addEventListener('submit', this.handleSubmit.bind(this));

    if (this.useSmartCaptcha) {
        this.initSmartCaptcha();
    }
};

VictoryFormHandler.prototype.showMessage = function(message, isSuccess) {
    if (!this.errorsDiv) return;
    this.errorsDiv.style.color = isSuccess ? 'green' : 'red';
    this.errorsDiv.innerHTML = message;
};

VictoryFormHandler.prototype.handleSubmit = async function(e) {
    e.preventDefault();
    if (this.submitBtn) this.submitBtn.disabled = true;

    try {
        const formData = new FormData(this.form);
        const response = await fetch(window.location.pathname + '?action=submit', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            this.showMessage(`Ошибка соединения с сервером: ${response.statusText}`, false);
            return;
        }

        const resp = await response.json();

        if (resp.success) {
            this.showMessage(resp.message, true);
            this.form.reset();
            if (window.grecaptcha) {
                // Reset reCAPTCHA if it exists on the form
                const recaptchaContainer = this.form.querySelector('.g-recaptcha');
                if (recaptchaContainer && recaptchaContainer.id) {
                     grecaptcha.reset();
                }
            }
        } else {
            const errorMessages = resp.errors ? '<br>' + Object.values(resp.errors).join('<br>') : '';
            this.showMessage(resp.message + errorMessages, false);
        }

    } catch (error) {
        this.showMessage('Ошибка сети или некорректный ответ сервера.', false);
        console.error('Form submission error:', error);
    } finally {
        if (this.submitBtn) this.submitBtn.disabled = false;
        if (this.useSmartCaptcha && this.smartCaptchaId !== null && window.smartCaptcha) {
            window.smartCaptcha.reset(this.smartCaptchaId);
        }
    }
};

VictoryFormHandler.prototype.initSmartCaptcha = function() {
    const container = document.getElementById(this.form.id + '_smartcaptcha');
    if (!container || !window.smartCaptcha) return;

    this.smartCaptchaId = window.smartCaptcha.render(container, {
        sitekey: container.dataset.sitekey,
        callback: this.handleSmartCaptchaToken.bind(this),
    });
};

VictoryFormHandler.prototype.handleSmartCaptchaToken = function(token) {
    const tokenInput = document.getElementById(this.form.id + '_smart_token');
    if (tokenInput) {
        tokenInput.value = token;
    }
}; 