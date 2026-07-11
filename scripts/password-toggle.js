// Adds a show/hide toggle button to every password field.
// Fields that ship their own toggle can opt out with data-custom-toggle.
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.dataset.customToggle !== undefined) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-label', 'Show password');
    button.innerHTML = '<i class="fa-solid fa-eye"></i>';

    button.addEventListener('click', function () {
      const icon = button.querySelector('i');
      const show = input.type === 'password';

      input.type = show ? 'text' : 'password';
      icon.classList.toggle('fa-eye', !show);
      icon.classList.toggle('fa-eye-slash', show);
    });

    wrapper.appendChild(button);
  });
});
