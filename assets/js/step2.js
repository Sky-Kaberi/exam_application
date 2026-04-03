const categoryMap = {
  'West Bengal': ['General', 'SC', 'ST', 'OBC-A', 'OBC-B', 'General-EWS'],
  Others: ['General', 'SC', 'ST', 'OBC']
};

const tabButtons = document.querySelectorAll('.tab-btn[data-tab]');
const tabPanels = document.querySelectorAll('.tab-panel');
const basicForm = document.getElementById('basicInfoForm');
const addressForm = document.getElementById('addressForm');
const coursesForm = document.getElementById('coursesForm');
const imagesForm = document.getElementById('imagesForm');

const domicileField = basicForm.elements.domicile;
const categoryField = basicForm.elements.category;
const pwdField = basicForm.elements.pwd_status;
const disabilityTypeFieldWrap = document.getElementById('disabilityTypeField');
const disabilityPercentageFieldWrap = document.getElementById('disabilityPercentageField');

const statuses = {
  basic: document.getElementById('basicStatus'),
  address: document.getElementById('addressStatus'),
  courses: document.getElementById('coursesStatus'),
  image: document.getElementById('imagesStatus')
};

let addressReference = null;
let courseOptions = null;

function setStatus(name, message, ok = false) {
  const node = statuses[name];
  if (!node) return;
  node.textContent = message;
  node.style.color = ok ? '#0a7a35' : '#b42318';
}

function switchTab(tabName) {
  tabButtons.forEach((button) => button.classList.toggle('active', button.dataset.tab === tabName));
  tabPanels.forEach((panel) => panel.classList.toggle('active', panel.id === `tab-${tabName}`));
  fetch('../ajax/progress.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ last_tab: tabName })
  }).catch(() => null);
}

function clearErrors(form) {
  form.querySelectorAll('.error').forEach((n) => { n.textContent = ''; });
}

function showErrors(form, errors = {}) {
  Object.entries(errors).forEach(([fieldName, message]) => {
    const node = form.querySelector(`[data-error-for="${fieldName}"]`);
    if (node) node.textContent = message;
  });
}

function renderCategoryOptions(selectedValue = '') {
  const domicile = domicileField.value;
  const options = categoryMap[domicile] || [];
  categoryField.innerHTML = '<option value="">' + (options.length ? 'Select' : 'Select domicile first') + '</option>';
  options.forEach((value) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    option.selected = value === selectedValue;
    categoryField.appendChild(option);
  });
}

function toggleDisabilityFields() {
  const show = pwdField.value === 'Yes';
  disabilityTypeFieldWrap.style.display = show ? '' : 'none';
  disabilityPercentageFieldWrap.style.display = show ? '' : 'none';
}

function buildSelect(select, options, selected = '') {
  select.innerHTML = '<option value="">Select</option>';
  options.forEach((value) => {
    const op = document.createElement('option');
    op.value = value;
    op.textContent = value;
    op.selected = value === selected;
    select.appendChild(op);
  });
}

function syncPermanentAddress() {
  if (!addressForm.elements.same_as_correspondence.checked) return;
  ['premises', 'sub_locality', 'locality', 'pin_code'].forEach((key) => {
    addressForm.elements[`perm_${key}`].value = addressForm.elements[`corr_${key}`].value;
  });

  const corrCountry = addressForm.elements.corr_country.value;
  const corrState = addressForm.elements.corr_state.value;
  const corrDistrict = addressForm.elements.corr_district.value;

  addressForm.elements.perm_country.value = corrCountry;
  buildSelect(
    addressForm.elements.perm_state,
    addressReference?.states_by_country?.[corrCountry] || [],
    corrState
  );
  buildSelect(
    addressForm.elements.perm_district,
    addressReference?.districts_by_state?.[corrState] || [],
    corrDistrict
  );
}

function validateBasic(payload) {
  const errors = {};
  if (!payload.domicile) errors.domicile = 'Please select domicile.';
  if (!payload.religion) errors.religion = 'Please select religion.';
  if (!(categoryMap[payload.domicile] || []).includes(payload.category)) errors.category = 'Invalid category.';
  if (payload.pwd_status === 'Yes' && !payload.disability_type) errors.disability_type = 'Select disability type.';
  if (payload.pwd_status === 'Yes' && !payload.disability_percentage) errors.disability_percentage = 'Enter disability percentage.';
  if (!/^\d{4}$/.test((payload.year_of_passing || '').trim())) errors.year_of_passing = 'Year should be 4 digits.';
  if (!payload.qualifying_examination?.trim()) errors.qualifying_examination = 'Required field.';
  if (!payload.institute_name_address?.trim()) errors.institute_name_address = 'Required field.';
  return errors;
}

function validateAddress(payload) {
  const errors = {};
  ['corr', 'perm'].forEach((prefix) => {
    if (!payload[`${prefix}_premises`]?.trim()) errors[`${prefix}_premises`] = 'Required field.';
    if (!payload[`${prefix}_locality`]?.trim()) errors[`${prefix}_locality`] = 'Required field.';
    if (!/^[1-9][0-9]{5}$/.test(payload[`${prefix}_pin_code`] || '')) errors[`${prefix}_pin_code`] = 'Invalid PIN code.';
    if (!payload[`${prefix}_country`]) errors[`${prefix}_country`] = 'Select country.';
    if (!payload[`${prefix}_state`]) errors[`${prefix}_state`] = 'Select state.';
    if (!payload[`${prefix}_district`]) errors[`${prefix}_district`] = 'Select district.';
  });
  return errors;
}

function validateCourses(payload) {
  const errors = {};
  if (!payload.course_group_1) errors.course_group_1 = 'Select Group-1 course.';
  if (!payload.course_group_2) errors.course_group_2 = 'Select Group-2 course.';
  if (!payload.exam_city) errors.exam_city = 'Select exam city.';
  return errors;
}

function validateImages() {
  const errors = {};
  const photo = imagesForm.elements.photo.files[0];
  const sign = imagesForm.elements.signature.files[0];
  const validExt = (f) => ['image/jpeg', 'image/pjpeg'].includes(f.type);
  if (photo) {
    if (!validExt(photo)) errors.photo = 'Photograph must be JPG/JPEG.';
    if (photo.size < 10 * 1024 || photo.size > 200 * 1024) errors.photo = 'Photograph size must be 10KB-200KB.';
  }
  if (sign) {
    if (!validExt(sign)) errors.signature = 'Signature must be JPG/JPEG.';
    if (sign.size < 4 * 1024 || sign.size > 30 * 1024) errors.signature = 'Signature size must be 4KB-30KB.';
  }
  return errors;
}

async function submitJsonForm(form, url, payload, tabName, validator) {
  clearErrors(form);
  setStatus(tabName, '');
  const localErrors = validator(payload);
  if (Object.keys(localErrors).length) {
    showErrors(form, localErrors);
    setStatus(tabName, 'Please correct highlighted errors.');
    return false;
  }

  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const data = await response.json();

  if (!response.ok || !data.success) {
    showErrors(form, data.errors || {});
    setStatus(tabName, data.message || 'Unable to save.');
    return false;
  }

  setStatus(tabName, data.message || 'Saved successfully.', true);
  return true;
}

async function loadBasicInfo() {
  const response = await fetch('../ajax/step2_basic.php');
  const data = await response.json();
  Object.entries(data.data).forEach(([key, value]) => { if (basicForm.elements[key]) basicForm.elements[key].value = value ?? ''; });
  renderCategoryOptions(data.data.category || '');
  toggleDisabilityFields();
}

async function loadAddressInfo() {
  const response = await fetch('../ajax/step2_address.php');
  const data = await response.json();
  addressReference = data.reference;

  ['corr_country', 'perm_country'].forEach((name) => buildSelect(addressForm.elements[name], addressReference.countries, data.data[name] || ''));
  const renderStates = (prefix) => buildSelect(addressForm.elements[`${prefix}_state`], addressReference.states_by_country[addressForm.elements[`${prefix}_country`].value] || [], data.data[`${prefix}_state`] || '');
  const renderDistricts = (prefix) => buildSelect(addressForm.elements[`${prefix}_district`], addressReference.districts_by_state[addressForm.elements[`${prefix}_state`].value] || [], data.data[`${prefix}_district`] || '');

  Object.entries(data.data).forEach(([key, value]) => {
    if (addressForm.elements[key] && !['corr_country', 'corr_state', 'corr_district', 'perm_country', 'perm_state', 'perm_district'].includes(key)) {
      if (addressForm.elements[key].type === 'checkbox') {
        addressForm.elements[key].checked = Number(value) === 1;
      } else {
        addressForm.elements[key].value = value ?? '';
      }
    }
  });

  renderStates('corr'); renderStates('perm'); renderDistricts('corr'); renderDistricts('perm');

  ['corr_country', 'perm_country'].forEach((name) => {
    addressForm.elements[name].addEventListener('change', () => {
      const prefix = name.startsWith('corr') ? 'corr' : 'perm';
      buildSelect(addressForm.elements[`${prefix}_state`], addressReference.states_by_country[addressForm.elements[name].value] || []);
      buildSelect(addressForm.elements[`${prefix}_district`], []);
      syncPermanentAddress();
    });
  });

  ['corr_state', 'perm_state'].forEach((name) => {
    addressForm.elements[name].addEventListener('change', () => {
      const prefix = name.startsWith('corr') ? 'corr' : 'perm';
      buildSelect(addressForm.elements[`${prefix}_district`], addressReference.districts_by_state[addressForm.elements[name].value] || []);
      syncPermanentAddress();
    });
  });
}

async function loadCourseInfo() {
  const response = await fetch('../ajax/step2_courses.php');
  const data = await response.json();
  courseOptions = data.options;
  buildSelect(coursesForm.elements.course_group_1, courseOptions.group_1, data.data.course_group_1 || '');
  buildSelect(coursesForm.elements.course_group_2, courseOptions.group_2, data.data.course_group_2 || '');
  buildSelect(coursesForm.elements.exam_city, courseOptions.exam_cities, data.data.exam_city || '');
}

function renderExistingImage(id, path, label) {
  const node = document.getElementById(id);
  node.innerHTML = path ? `<p class="muted">Saved ${label}</p><img src="../public/${path}" alt="${label}">` : '';
}

async function loadImagesInfo() {
  const response = await fetch('../ajax/step2_images.php');
  const data = await response.json();
  renderExistingImage('photoPreview', data.data.photo_path, 'photograph');
  renderExistingImage('signaturePreview', data.data.signature_path, 'signature');
}

basicForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(basicForm).entries());
  await submitJsonForm(basicForm, '../ajax/step2_basic.php', payload, 'basic', validateBasic);
});

addressForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(addressForm).entries());
  payload.same_as_correspondence = addressForm.elements.same_as_correspondence.checked ? 1 : 0;
  await submitJsonForm(addressForm, '../ajax/step2_address.php', payload, 'address', validateAddress);
});

coursesForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = Object.fromEntries(new FormData(coursesForm).entries());
  await submitJsonForm(coursesForm, '../ajax/step2_courses.php', payload, 'courses', validateCourses);
});

imagesForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  clearErrors(imagesForm);
  const errors = validateImages();
  if (Object.keys(errors).length) {
    showErrors(imagesForm, errors);
    setStatus('image', 'Please correct highlighted errors.');
    return;
  }

  const response = await fetch('../ajax/step2_images.php', { method: 'POST', body: new FormData(imagesForm) });
  const data = await response.json();
  if (!response.ok || !data.success) {
    showErrors(imagesForm, data.errors || {});
    setStatus('image', data.message || 'Unable to save images.');
    return;
  }

  renderExistingImage('photoPreview', data.data.photo_path, 'photograph');
  renderExistingImage('signaturePreview', data.data.signature_path, 'signature');
  imagesForm.reset();
  setStatus('image', data.message || 'Saved successfully.', true);
});

domicileField.addEventListener('change', () => renderCategoryOptions(''));
pwdField.addEventListener('change', toggleDisabilityFields);
addressForm.elements.same_as_correspondence.addEventListener('change', syncPermanentAddress);
['corr_premises', 'corr_sub_locality', 'corr_locality', 'corr_country', 'corr_state', 'corr_district', 'corr_pin_code'].forEach((name) => {
  addressForm.elements[name].addEventListener('input', syncPermanentAddress);
  addressForm.elements[name].addEventListener('change', syncPermanentAddress);
});

document.querySelectorAll('button[data-next-tab]').forEach((button) => {
  button.addEventListener('click', async () => {
    const form = button.closest('form');
    form.requestSubmit();
    setTimeout(() => switchTab(button.dataset.nextTab), 150);
  });
});

tabButtons.forEach((button) => button.addEventListener('click', () => switchTab(button.dataset.tab)));

Promise.all([loadBasicInfo(), loadAddressInfo(), loadCourseInfo(), loadImagesInfo()]).then(async () => {
  const response = await fetch('../ajax/progress.php');
  const data = await response.json();
  switchTab(window.step2InitialTab || data.progress.last_tab || data.resume_tab || 'basic');
}).catch(() => {
  switchTab(window.step2InitialTab || 'basic');
});
