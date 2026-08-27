const steps = [...document.querySelectorAll('.form-step')];
const progressSteps = [...document.querySelectorAll('.steps li')];
const progressControl = document.querySelector('#progress-control');
const progressThumb = document.querySelector('.scrollbar-thumb');
const scrollLeft = document.querySelector('.scroll-left');
const scrollRight = document.querySelector('.scroll-right');
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
  if (progressControl) {
    progressControl.setAttribute('aria-valuenow', step);
  }
  if (progressThumb && progressControl) {
    progressThumb.style.left = `${24 + (step / (steps.length - 1)) * (progressControl.clientWidth - 48)}px`;
  }
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

function setStepFromPointer(event) {
  const bounds = progressControl.getBoundingClientRect();
  const position = Math.max(0, Math.min(bounds.width - 48, event.clientX - bounds.left - 24));
  showStep(Math.round((position / (bounds.width - 48)) * (steps.length - 1)));
}

if (progressControl) {
  progressControl.addEventListener('pointerdown', event => {
    progressControl.setPointerCapture(event.pointerId);
    setStepFromPointer(event);
  });
  progressControl.addEventListener('pointermove', event => {
    if (progressControl.hasPointerCapture(event.pointerId)) setStepFromPointer(event);
  });
  progressControl.addEventListener('keydown', event => {
    if (event.key === 'ArrowRight' || event.key === 'ArrowUp') showStep(Math.min(currentStep + 1, steps.length - 1));
    if (event.key === 'ArrowLeft' || event.key === 'ArrowDown') showStep(Math.max(currentStep - 1, 0));
    if (event.key === 'Home') showStep(0);
    if (event.key === 'End') showStep(steps.length - 1);
  });
}
scrollLeft?.addEventListener('click', () => showStep(Math.max(currentStep - 1, 0)));
scrollRight?.addEventListener('click', () => showStep(Math.min(currentStep + 1, steps.length - 1)));
listingForm.addEventListener('submit', event => event.preventDefault());
showStep(0);