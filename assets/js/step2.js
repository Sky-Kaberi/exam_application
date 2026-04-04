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
const baseApplicationFee = 3000;
const courseFeeDisplay = document.getElementById('courseFeeDisplay');
let addressListenersBound = false;
let tabProgress = {
  step2_basic_completed: 0,
  step2_address_completed: 0,
  step2_courses_completed: 0,
  step2_images_completed: 0
};

const tabRequirements = {
  basic: [],
  address: ['step2_basic_completed'],
  courses: ['step2_basic_completed', 'step2_address_completed'],
  image: ['step2_basic_completed', 'step2_address_completed', 'step2_courses_completed']
};

async function apiGetJson(url) {
  const response = await fetch(`${url}${url.includes('?') ? '&' : '?'}t=${Date.now()}`, { cache: 'no-store' });
  const data = await response.json();
  return { response, data };
}

function setStatus(name, message, ok = false) {
  const node = statuses[name];
  if (!node) return;
  node.textContent = message;
  node.style.color = ok ? '#0a7a35' : '#b42318';
}

function canAccessTab(tabName) {
  const requirements = tabRequirements[tabName] || [];
  return requirements.every((key) => Number(tabProgress[key]) === 1);
}

function getBlockedTabMessage(tabName) {
  if (tabName === 'address') return 'Please save Basic Info before opening Correspondence Address.';
  if (tabName === 'courses') return 'Please save Basic Info and Address before opening Courses.';
  if (tabName === 'image') return 'Please save Basic Info, Address, and Courses before opening Image Upload.';
  return 'Please complete the previous section first.';
}

function refreshTabLocks() {
  tabButtons.forEach((button) => {
    const allowed = canAccessTab(button.dataset.tab);
    button.disabled = !allowed;
    button.style.opacity = allowed ? '1' : '0.6';
    button.style.cursor = allowed ? 'pointer' : 'not-allowed';
  });
}

function switchTab(tabName, persist = true) {
  if (!canAccessTab(tabName)) {
    setStatus('basic', getBlockedTabMessage(tabName));
    return false;
  }
  tabButtons.forEach((button) => button.classList.toggle('active', button.dataset.tab === tabName));
  tabPanels.forEach((panel) => panel.classList.toggle('active', panel.id === `tab-${tabName}`));
  if (persist) {
    fetch(`../ajax/progress.php?t=${Date.now()}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ last_tab: tabName }),
      cache: 'no-store'
    }).catch(() => null);
  }

  reloadTabData(tabName).catch(() => {
    setStatus(tabName, 'Unable to refresh saved values. Please try again.');
  });
  return true;
}

async function reloadTabData(tabName) {
  if (tabName === 'basic') return loadBasicInfo();
  if (tabName === 'address') return loadAddressInfo();
  if (tabName === 'courses') return loadCourseInfo();
  if (tabName === 'image') return loadImagesInfo();
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

function getAddressOptions(type, key) {
  if (!addressReference) return [];
  if (type === 'states') return addressReference.states_by_country?.[key] || [];
  if (type === 'districts') return addressReference.districts_by_state?.[key] || [];
  return [];
}

function populateAddressDropdowns(prefix, values = {}) {
  const country = values.country || '';
  const state = values.state || '';
  const district = values.district || '';

  const countryField = addressForm.elements[`${prefix}_country`];
  const stateField = addressForm.elements[`${prefix}_state`];
  const districtField = addressForm.elements[`${prefix}_district`];

  countryField.value = country;

  buildSelect(stateField, getAddressOptions('states', country), state);
  const selectedState = stateField.value;

  buildSelect(districtField, getAddressOptions('districts', selectedState), district);
}

function syncPermanentAddress() {
  if (!addressForm.elements.same_as_correspondence.checked) return;
  ['premises', 'sub_locality', 'locality', 'pin_code'].forEach((key) => {
    addressForm.elements[`perm_${key}`].value = addressForm.elements[`corr_${key}`].value;
  });

  const corrCountry = addressForm.elements.corr_country.value;
  const corrState = addressForm.elements.corr_state.value;
  const corrDistrict = addressForm.elements.corr_district.value;

  populateAddressDropdowns('perm', {
    country: corrCountry,
    state: corrState,
    district: corrDistrict
  });
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

function calculateCourseFee(group1, group2) {
  const hasGroup1 = Boolean((group1 || '').trim());
  const hasGroup2 = Boolean((group2 || '').trim());
  if (hasGroup1 && hasGroup2) return baseApplicationFee * 2;
  if (hasGroup1 || hasGroup2) return baseApplicationFee;
  return 0;
}

function updateCourseFeeDisplay() {
  if (!courseFeeDisplay) return;
  const fee = calculateCourseFee(coursesForm.elements.course_group_1.value, coursesForm.elements.course_group_2.value);
  courseFeeDisplay.textContent = `INR ${fee}/-`;
}

function validateCourses(payload) {
  const errors = {};
  if (!payload.course_group_1 && !payload.course_group_2) {
    errors.course_group_1 = 'Select one course from Group-1 or Group-2.';
    errors.course_group_2 = 'Select one course from Group-1 or Group-2.';
  }
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

  if (tabName === 'basic') tabProgress.step2_basic_completed = 1;
  if (tabName === 'address') tabProgress.step2_address_completed = 1;
  if (tabName === 'courses') tabProgress.step2_courses_completed = 1;
  refreshTabLocks();
  setStatus(tabName, data.message || 'Saved successfully.', true);
  return true;
}

async function loadBasicInfo() {
  const { response, data } = await apiGetJson('../ajax/step2_basic.php');
  if (!response.ok) throw new Error('Unable to load basic info.');
  if (!data.success || !data.data) throw new Error(data.message || 'Unable to load basic info.');
  Object.entries(data.data).forEach(([key, value]) => { if (basicForm.elements[key]) basicForm.elements[key].value = value ?? ''; });
  renderCategoryOptions(data.data.category || '');
  toggleDisabilityFields();
}

async function loadAddressInfo() {
  const { response, data } = await apiGetJson('../ajax/step2_address.php');
  if (!response.ok) throw new Error('Unable to load address info.');
  if (!data.success || !data.data || !data.reference) throw new Error(data.message || 'Unable to load address info.');
  addressReference = data.reference;

  ['corr_country', 'perm_country'].forEach((name) => buildSelect(addressForm.elements[name], addressReference.countries, data.data[name] || ''));

  Object.entries(data.data).forEach(([key, value]) => {
    if (addressForm.elements[key] && !['corr_country', 'corr_state', 'corr_district', 'perm_country', 'perm_state', 'perm_district'].includes(key)) {
      if (addressForm.elements[key].type === 'checkbox') {
        addressForm.elements[key].checked = Number(value) === 1;
      } else {
        addressForm.elements[key].value = value ?? '';
      }
    }
  });

  populateAddressDropdowns('corr', {
    country: data.data.corr_country || '',
    state: data.data.corr_state || '',
    district: data.data.corr_district || ''
  });
  populateAddressDropdowns('perm', {
    country: data.data.perm_country || '',
    state: data.data.perm_state || '',
    district: data.data.perm_district || ''
  });

  if (!addressListenersBound) {
    ['corr_country', 'perm_country'].forEach((name) => {
      addressForm.elements[name].addEventListener('change', () => {
        const prefix = name.startsWith('corr') ? 'corr' : 'perm';
        populateAddressDropdowns(prefix, { country: addressForm.elements[name].value });
        syncPermanentAddress();
      });
    });

    ['corr_state', 'perm_state'].forEach((name) => {
      addressForm.elements[name].addEventListener('change', () => {
        const prefix = name.startsWith('corr') ? 'corr' : 'perm';
        const currentCountry = addressForm.elements[`${prefix}_country`].value;
        populateAddressDropdowns(prefix, {
          country: currentCountry,
          state: addressForm.elements[name].value
        });
        syncPermanentAddress();
      });
    });
    addressListenersBound = true;
  }

  syncPermanentAddress();
}

async function loadCourseInfo() {
  const { response, data } = await apiGetJson('../ajax/step2_courses.php');
  if (!response.ok) throw new Error('Unable to load courses.');
  if (!data.success || !data.data || !data.options) throw new Error(data.message || 'Unable to load courses.');
  courseOptions = data.options;
  buildSelect(coursesForm.elements.course_group_1, courseOptions.group_1, data.data.course_group_1 || '');
  buildSelect(coursesForm.elements.course_group_2, courseOptions.group_2, data.data.course_group_2 || '');
  buildSelect(coursesForm.elements.exam_city, courseOptions.exam_cities, data.data.exam_city || '');
  updateCourseFeeDisplay();
}

function renderExistingImage(id, path, label, cacheToken = '') {
  const node = document.getElementById(id);
  if (!path) {
    node.innerHTML = '';
    return;
  }

  const token = cacheToken ? `?v=${encodeURIComponent(cacheToken)}` : '';
  node.innerHTML = `<p class="muted">Saved ${label}</p><img src="../public/${path}${token}" alt="${label}">`;
}

async function loadImagesInfo() {
  const { response, data } = await apiGetJson('../ajax/step2_images.php');
  if (!response.ok) throw new Error('Unable to load image details.');
  if (!data.success || !data.data) throw new Error(data.message || 'Unable to load image details.');
  const cacheToken = Date.now().toString();
  renderExistingImage('photoPreview', data.data.photo_path, 'photograph', cacheToken);
  renderExistingImage('signaturePreview', data.data.signature_path, 'signature', cacheToken);
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

coursesForm.elements.course_group_1.addEventListener('change', updateCourseFeeDisplay);
coursesForm.elements.course_group_2.addEventListener('change', updateCourseFeeDisplay);

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

  const cacheToken = Date.now().toString();
  renderExistingImage('photoPreview', data.data.photo_path, 'photograph', cacheToken);
  renderExistingImage('signaturePreview', data.data.signature_path, 'signature', cacheToken);
  imagesForm.reset();
  tabProgress.step2_images_completed = 1;
  refreshTabLocks();
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
    let saved = false;
    if (form === basicForm) {
      const payload = Object.fromEntries(new FormData(basicForm).entries());
      saved = await submitJsonForm(basicForm, '../ajax/step2_basic.php', payload, 'basic', validateBasic);
    } else if (form === addressForm) {
      const payload = Object.fromEntries(new FormData(addressForm).entries());
      payload.same_as_correspondence = addressForm.elements.same_as_correspondence.checked ? 1 : 0;
      saved = await submitJsonForm(addressForm, '../ajax/step2_address.php', payload, 'address', validateAddress);
    } else if (form === coursesForm) {
      const payload = Object.fromEntries(new FormData(coursesForm).entries());
      saved = await submitJsonForm(coursesForm, '../ajax/step2_courses.php', payload, 'courses', validateCourses);
    }
    if (saved) {
      switchTab(button.dataset.nextTab);
    }
  });
});

tabButtons.forEach((button) => button.addEventListener('click', () => {
  const targetTab = button.dataset.tab;
  if (!switchTab(targetTab)) {
    setStatus('basic', getBlockedTabMessage(targetTab));
  }
}));

Promise.allSettled([loadBasicInfo(), loadAddressInfo(), loadCourseInfo(), loadImagesInfo()]).then(async (results) => {
  const failed = results.filter((result) => result.status === 'rejected');
  if (failed.length) {
    setStatus('basic', 'Some saved values could not be loaded. Please refresh and try again.');
  }

  let resumeTab = 'basic';
  try {
    resumeTab = await refreshProgress();
  } catch (error) {
    // no-op: keep defaults
  }

  refreshTabLocks();
  const preferredTab = window.step2InitialTab || resumeTab || 'basic';
  if (!switchTab(preferredTab, false)) {
    switchTab(resumeTab === 'preview' ? 'image' : resumeTab, false) || switchTab('basic', false);
  }
}).catch(() => {
  refreshTabLocks();
  switchTab('basic', false);
});

window.addEventListener('pageshow', async () => {
  try {
    await refreshProgress();
    refreshTabLocks();
    const activeTab = document.querySelector('.tab-btn.active')?.dataset.tab || 'basic';
    await reloadTabData(activeTab);
  } catch (error) {
    // no-op
  }
});
async function refreshProgress() {
  const { response, data } = await apiGetJson('../ajax/progress.php');
  if (!response.ok || !data.success) {
    throw new Error('Unable to load application progress.');
  }

  tabProgress = {
    step2_basic_completed: Number(data.progress.step2_basic_completed || 0),
    step2_address_completed: Number(data.progress.step2_address_completed || 0),
    step2_courses_completed: Number(data.progress.step2_courses_completed || 0),
    step2_images_completed: Number(data.progress.step2_images_completed || 0)
  };
  return data.resume_tab || 'basic';
}
