const steps = [...document.querySelectorAll('.form-step')];
const progressSteps = [...document.querySelectorAll('.steps li')];
const previousButton = document.querySelector('.previous');
const nextButton = document.querySelector('.next');
const listingForm = document.querySelector('.listing-form');
let currentStep = 0;

function updateSummary() {
  const values = {
    '#summary-name': document.querySelector('#venue-name').value || '—',
    '#summary-type': document.querySelector('input[name="venue-type"]:checked')?.nextElementSibling.textContent || '—',
    '#summary-location': document.querySelector('#city').value || '—',
    '#summary-capacity': `${document.querySelector('#min-capacity').value || '—'}–${document.querySelector('#max-capacity').value || '—'}`,
    '#summary-price': `$${document.querySelector('#base-price').value || '—'}`
  };

  Object.entries(values).forEach(([selector, value]) => {
    document.querySelector(selector).textContent = value;
  });
}

function showStep(step) {
  currentStep = step;
  steps.forEach((panel, index) => panel.classList.toggle('active-step', index === step));
  progressSteps.forEach((item, index) => {
    item.classList.toggle('current', index === step);
    item.classList.toggle('complete', index < step);
    item.querySelector('span').textContent = index < step ? '✓' : String(index + 1);
  });
  previousButton.disabled = step === 0;
  nextButton.innerHTML = step === steps.length - 1
    ? 'Submit for Review <span aria-hidden="true">✓</span>'
    : 'Next <span aria-hidden="true">→</span>';

  if (step === steps.length - 1) updateSummary();
}

nextButton.addEventListener('click', () => {
  if (currentStep < steps.length - 1) showStep(currentStep + 1);
  else listingForm.submit();
});

previousButton.addEventListener('click', () => {
  if (currentStep > 0) showStep(currentStep - 1);
});
listingForm.addEventListener('submit', event => event.preventDefault());
showStep(0);