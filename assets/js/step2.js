const categoryMap = {
  'West Bengal': ['General', 'SC', 'ST', 'OBC-A', 'OBC-B', 'General-EWS'],
  Others: ['General', 'SC', 'ST', 'OBC']
};

const tabButtons = document.querySelectorAll('.tab-btn');
const tabPanels = document.querySelectorAll('.tab-panel');
const form = document.getElementById('basicInfoForm');
const domicileField = form.elements.domicile;
const categoryField = form.elements.category;
const pwdField = form.elements.pwd_status;
const disabilityTypeFieldWrap = document.getElementById('disabilityTypeField');
const disabilityPercentageFieldWrap = document.getElementById('disabilityPercentageField');
const statusNode = document.getElementById('basicStatus');

function switchTab(tabName) {
  tabButtons.forEach((button) => {
    button.classList.toggle('active', button.dataset.tab === tabName);
  });
  tabPanels.forEach((panel) => {
    panel.classList.toggle('active', panel.id === `tab-${tabName}`);
  });
}

function renderCategoryOptions(selectedValue = '') {
  const domicile = domicileField.value;
  const options = categoryMap[domicile] || [];

  categoryField.innerHTML = '';
  const defaultOption = document.createElement('option');
  defaultOption.value = '';
  defaultOption.textContent = options.length ? 'Select' : 'Select domicile first';
  categoryField.appendChild(defaultOption);

  options.forEach((value) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    if (value === selectedValue) {
      option.selected = true;
    }
    categoryField.appendChild(option);
  });
}

function toggleDisabilityFields() {
  const show = pwdField.value === 'Yes';
  disabilityTypeFieldWrap.style.display = show ? '' : 'none';
  disabilityPercentageFieldWrap.style.display = show ? '' : 'none';
  if (!show) {
    form.elements.disability_type.value = '';
    form.elements.disability_percentage.value = '';
  }
}

function clearErrors() {
  form.querySelectorAll('.error').forEach((node) => {
    node.textContent = '';
  });
}

function showErrors(errors) {
  Object.entries(errors).forEach(([fieldName, message]) => {
    const node = form.querySelector(`[data-error-for="${fieldName}"]`);
    if (node) {
      node.textContent = message;
    }
  });
}

async function loadBasicInfo() {
  const response = await fetch('../ajax/step2_basic.php');
  const data = await response.json();
  if (!response.ok || !data.success) {
    throw new Error(data.message || 'Unable to load saved data.');
  }

  Object.entries(data.data).forEach(([key, value]) => {
    if (form.elements[key]) {
      form.elements[key].value = value ?? '';
    }
  });

  renderCategoryOptions(data.data.category || '');
  toggleDisabilityFields();
}

function validateBasicInfo(payload) {
  const errors = {};

  if (payload.nationality !== 'Indian') errors.nationality = 'Only Indian is allowed.';
  if (!payload.domicile) errors.domicile = 'Please select domicile.';
  if (!payload.religion) errors.religion = 'Please select religion.';

  const categoryOptions = categoryMap[payload.domicile] || [];
  if (!categoryOptions.includes(payload.category)) {
    errors.category = 'Select a valid category based on domicile.';
  }

  if (payload.pwd_status === 'Yes') {
    if (!payload.disability_type) errors.disability_type = 'Select type of disability.';
    if (payload.disability_percentage === '' || Number.isNaN(Number(payload.disability_percentage))) {
      errors.disability_percentage = 'Enter numeric disability percentage.';
    }
  }

  if (!payload.qualifying_examination.trim()) errors.qualifying_examination = 'Qualifying examination is required.';
  if (!payload.year_of_passing.trim()) errors.year_of_passing = 'Year of passing is required.';
  if (!/^\d{4}$/.test(payload.year_of_passing.trim())) errors.year_of_passing = 'Year must be 4 digits.';
  if (!payload.institute_name_address.trim()) errors.institute_name_address = 'Institute name and address is required.';

  return errors;
}

async function saveBasicInfo(event) {
  event.preventDefault();
  clearErrors();
  statusNode.textContent = '';

  const payload = Object.fromEntries(new FormData(form).entries());
  const localErrors = validateBasicInfo(payload);
  if (Object.keys(localErrors).length) {
    showErrors(localErrors);
    statusNode.textContent = 'Please correct highlighted errors.';
    statusNode.style.color = '#b42318';
    return;
  }

  try {
    const response = await fetch('../ajax/step2_basic.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await response.json();

    if (!response.ok || !data.success) {
      if (data.errors) {
        showErrors(data.errors);
      }
      statusNode.textContent = data.message || 'Unable to save.';
      statusNode.style.color = '#b42318';
      return;
    }

    statusNode.textContent = data.message || 'Saved successfully.';
    statusNode.style.color = '#0a7a35';
  } catch (error) {
    statusNode.textContent = 'Unable to save due to network/server error.';
    statusNode.style.color = '#b42318';
  }
}

tabButtons.forEach((button) => {
  button.addEventListener('click', () => switchTab(button.dataset.tab));
});

domicileField.addEventListener('change', () => renderCategoryOptions(''));
pwdField.addEventListener('change', toggleDisabilityFields);
form.addEventListener('submit', saveBasicInfo);

loadBasicInfo().catch((error) => {
  statusNode.textContent = error.message;
  statusNode.style.color = '#b42318';
});
