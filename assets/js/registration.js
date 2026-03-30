const form = document.getElementById('registrationForm');
const appBox = document.getElementById('appBox');
const verificationState = { mobile: false, email: false };
const salutations = ['late', 'mr', 'ms', 'mrs', 'dr', 'prof'];

const identificationTypeField = form.elements.identification_type;
const identificationNoLabel = document.getElementById('identificationNoLabel');
const identificationNoInput = document.getElementById('identificationNoInput');

function updateIdentificationNoLabel() {
  const type = (identificationTypeField.value || '').trim();
  const dynamicLabel = type ? `${type} No.` : 'Identification Number';
  identificationNoLabel.textContent = dynamicLabel;
  identificationNoInput.placeholder = dynamicLabel;
}

async function postData(url, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  return response.json();
}

function setStatus(id, message, ok) {
  const node = document.getElementById(id);
  node.textContent = message;
  node.className = `status ${ok ? 'success' : 'error'}`;
}

function isAlphabeticName(value) {
  return /^[A-Za-z ]+$/.test(value);
}

function includesSalutation(value) {
  const tokens = value
    .toLowerCase()
    .replace(/\./g, '')
    .split(/\s+/)
    .filter(Boolean);
  return tokens.some((token) => salutations.includes(token));
}

function validateFormData(formData) {
  const names = [
    { key: 'candidate_name', label: 'Candidate name', checkSalutation: false },
    { key: 'father_name', label: "Father's name", checkSalutation: true },
    { key: 'mother_name', label: "Mother's name", checkSalutation: true }
  ];

  for (const nameField of names) {
    const value = (formData[nameField.key] || '').trim();
    if (value.length === 0) {
      return `${nameField.label} is required.`;
    }
    if (value.length > 46) {
      return `${nameField.label} must be maximum 46 characters.`;
    }
    if (!isAlphabeticName(value)) {
      return `${nameField.label} can only contain letters and spaces.`;
    }
    if (nameField.checkSalutation && includesSalutation(value)) {
      return `${nameField.label} must not include salutations like Late, Mr., Ms., Mrs., Dr., Prof.`;
    }
  }

  const allowedGenders = ['Male', 'Female', 'Third Gender'];
  if (!allowedGenders.includes(formData.gender)) {
    return 'Please select a valid gender.';
  }

  const identificationTypes = [
    'School ID card',
    'Voter ID',
    'Passport',
    'Ration Card with Photograph',
    'Class 10 admit card with Photograph',
    'Any other Valid Govt. Identity card With Photograph'
  ];
  if (!identificationTypes.includes(formData.identification_type)) {
    return 'Please select a valid identification type.';
  }

  if (!formData.date_of_birth) {
    return 'Date of birth is required.';
  }

  const password = formData.password || '';
  if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*\-]).{8,13}$/.test(password)) {
    return 'Password must be 8-13 chars and include uppercase, lowercase, number and special character (!@#$%^&*-).';
  }

  if (password !== (formData.confirm_password || '')) {
    return 'Confirm password must match password.';
  }

  if (!(formData.security_pin || '').trim()) {
    return 'Security PIN is required.';
  }

  return null;
}

async function sendOtp(channel) {
  const key = channel === 'mobile' ? 'mobile_no' : 'email_id';
  const payload = { channel, recipient: form[key].value.trim() };
  const data = await postData('../ajax/send_otp.php', payload);
  setStatus(`${channel}Status`, data.message + (data.debug_otp ? ` Demo OTP: ${data.debug_otp}` : ''), data.success);
}

async function verifyOtp(channel) {
  const recipientKey = channel === 'mobile' ? 'mobile_no' : 'email_id';
  const otpKey = channel === 'mobile' ? 'mobile_otp' : 'email_otp';
  const data = await postData('../ajax/verify_otp.php', {
    channel,
    recipient: form[recipientKey].value.trim(),
    otp: form[otpKey].value.trim()
  });
  verificationState[channel] = !!data.success;
  setStatus(`${channel}Status`, data.message, data.success);
}

document.getElementById('sendMobileOtp').addEventListener('click', () => sendOtp('mobile'));
document.getElementById('verifyMobileOtp').addEventListener('click', () => verifyOtp('mobile'));
document.getElementById('sendEmailOtp').addEventListener('click', () => sendOtp('email'));
document.getElementById('verifyEmailOtp').addEventListener('click', () => verifyOtp('email'));

identificationTypeField.addEventListener('change', updateIdentificationNoLabel);
updateIdentificationNoLabel();

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!verificationState.mobile || !verificationState.email) {
    alert('Please verify both mobile number and email before submitting.');
    return;
  }

  const formData = Object.fromEntries(new FormData(form).entries());
  const validationError = validateFormData(formData);
  if (validationError) {
    alert(validationError);
    return;
  }
  const data = await postData('../ajax/register.php', formData);

  if (data.success) {
    appBox.style.display = 'block';
    appBox.innerHTML = `<strong>Application Registered.</strong><br>Application ID: <strong>${data.application_id}</strong>`;
    form.reset();
    verificationState.mobile = false;
    verificationState.email = false;
  } else {
    alert(data.message || 'Registration failed');
  }
});
