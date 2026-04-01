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

  const rawBody = await response.text();
  let data;

  if (rawBody) {
    try {
      data = JSON.parse(rawBody);
    } catch (error) {
      throw new Error('Server returned an invalid response. Please try again.');
    }
  } else {
    data = null;
  }

  if (!response.ok) {
    const message = data && data.message ? data.message : `Request failed with status ${response.status}.`;
    throw new Error(message);
  }

  if (!data) {
    throw new Error('Server returned an empty response. Please try again.');
  }

  return data;
}

function setStatus(id, message, ok) {
  const node = document.getElementById(id);
  node.textContent = message;
  if (!message) {
    node.className = 'status';
    return;
  }
  node.className = `status ${ok ? 'success' : 'error'}`;
}

function includesSalutation(value) {
  const tokens = value
    .toLowerCase()
    .replace(/\./g, '')
    .split(/\s+/)
    .filter(Boolean);
  return tokens.some((token) => salutations.includes(token));
}

$.validator.addMethod(
  'lettersSpacesOnly',
  function lettersSpacesOnly(value, element) {
    return this.optional(element) || /^[A-Za-z ]+$/.test(value);
  },
  'Only letters and spaces are allowed.'
);

$.validator.addMethod(
  'noSalutation',
  function noSalutation(value, element) {
    return this.optional(element) || !includesSalutation(value);
  },
  'Do not use salutations like Late, Mr., Ms., Mrs., Dr., Prof.'
);

$.validator.addMethod(
  'strongPassword',
  function strongPassword(value, element) {
    return this.optional(element) || /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*\-]).{8,13}$/.test(value);
  },
  'Password must be 8-13 chars and include uppercase, lowercase, number and special character (!@#$%^&*-).'
);

const validator = $('#registrationForm').validate({
  errorElement: 'label',
  errorClass: 'error',
  errorPlacement(error, element) {
    if (element.parent('.otp-row').length) {
      error.insertAfter(element.parent());
      return;
    }
    error.insertAfter(element);
  },
  rules: {
    candidate_name: {
      required: true,
      maxlength: 46,
      lettersSpacesOnly: true
    },
    father_name: {
      required: true,
      maxlength: 46,
      lettersSpacesOnly: true,
      noSalutation: true
    },
    mother_name: {
      required: true,
      maxlength: 46,
      lettersSpacesOnly: true,
      noSalutation: true
    },
    date_of_birth: { required: true, dateISO: true },
    gender: { required: true },
    identification_type: { required: true },
    identification_no: { required: true, minlength: 3, maxlength: 50 },
    password: { required: true, strongPassword: true },
    confirm_password: { required: true, equalTo: '[name="password"]' },
    captcha_answer: { required: true, digits: true, maxlength: 3 },
    mobile_no: { required: true, digits: true, minlength: 10, maxlength: 10 },
    mobile_otp: { required: true, digits: true, minlength: 6, maxlength: 6 },
    email_id: { required: true, email: true },
    email_otp: { required: true, digits: true, minlength: 6, maxlength: 6 }
  },
  messages: {
    candidate_name: {
      required: 'Candidate name is required.',
      maxlength: 'Candidate name must be maximum 46 characters.'
    },
    father_name: {
      required: "Father's name is required.",
      maxlength: "Father's name must be maximum 46 characters."
    },
    mother_name: {
      required: "Mother's name is required.",
      maxlength: "Mother's name must be maximum 46 characters."
    },
    date_of_birth: { required: 'Date of birth is required.' },
    gender: { required: 'Please select gender.' },
    identification_type: { required: 'Please select identification type.' },
    identification_no: { required: 'Identification number is required.' },
    confirm_password: { equalTo: 'Confirm password must match password.' },
    captcha_answer: {
      required: 'Please solve the CAPTCHA question.',
      digits: 'CAPTCHA answer must contain digits only.',
      maxlength: 'CAPTCHA answer is too long.'
    },
    mobile_no: {
      required: 'Mobile number is required.',
      digits: 'Mobile number must contain digits only.',
      minlength: 'Mobile number must be exactly 10 digits.',
      maxlength: 'Mobile number must be exactly 10 digits.'
    },
    mobile_otp: {
      required: 'Mobile OTP is required.',
      digits: 'Mobile OTP must contain digits only.',
      minlength: 'Mobile OTP must be 6 digits.',
      maxlength: 'Mobile OTP must be 6 digits.'
    },
    email_id: { required: 'Email ID is required.', email: 'Enter a valid email ID.' },
    email_otp: {
      required: 'Email OTP is required.',
      digits: 'Email OTP must contain digits only.',
      minlength: 'Email OTP must be 6 digits.',
      maxlength: 'Email OTP must be 6 digits.'
    }
  }
});

async function sendOtp(channel) {
  const fieldName = channel === 'mobile' ? 'mobile_no' : 'email_id';
  const field = form.elements[fieldName];

  if (!validator.element(field)) {
    return;
  }

  const payload = { channel, recipient: field.value.trim() };

  try {
    const data = await postData('../ajax/send_otp.php', payload);
    verificationState[channel] = false;
    let message = data.message || '';
    if (channel === 'mobile' && data.display_otp) {
      message = `${message} OTP: ${data.display_otp}`;
    }
    setStatus(`${channel}Status`, message, data.success);
  } catch (error) {
    verificationState[channel] = false;
    setStatus(`${channel}Status`, error.message, false);
  }
}

async function verifyOtp(channel) {
  const recipientKey = channel === 'mobile' ? 'mobile_no' : 'email_id';
  const otpKey = channel === 'mobile' ? 'mobile_otp' : 'email_otp';
  const recipientField = form.elements[recipientKey];
  const otpField = form.elements[otpKey];

  const isRecipientValid = validator.element(recipientField);
  const isOtpValid = validator.element(otpField);
  if (!isRecipientValid || !isOtpValid) {
    return;
  }

  try {
    const data = await postData('../ajax/verify_otp.php', {
      channel,
      recipient: recipientField.value.trim(),
      otp: otpField.value.trim()
    });
    verificationState[channel] = !!data.success;
    setStatus(`${channel}Status`, data.message, data.success);
  } catch (error) {
    verificationState[channel] = false;
    setStatus(`${channel}Status`, error.message, false);
  }
}

document.getElementById('sendMobileOtp').addEventListener('click', () => sendOtp('mobile'));
document.getElementById('verifyMobileOtp').addEventListener('click', () => verifyOtp('mobile'));
document.getElementById('sendEmailOtp').addEventListener('click', () => sendOtp('email'));
document.getElementById('verifyEmailOtp').addEventListener('click', () => verifyOtp('email'));

identificationTypeField.addEventListener('change', updateIdentificationNoLabel);
updateIdentificationNoLabel();

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!validator.form()) {
    return;
  }

  if (!verificationState.mobile || !verificationState.email) {
    alert('Please verify both mobile number and email before submitting.');
    return;
  }

  const formData = Object.fromEntries(new FormData(form).entries());

  try {
    const data = await postData('../ajax/register.php', formData);

    if (data.success) {
      appBox.style.display = 'block';
      appBox.innerHTML = `<strong>Application Registered.</strong><br>Application ID: <strong>${data.application_id}</strong>`;
      form.reset();
      validator.resetForm();
      verificationState.mobile = false;
      verificationState.email = false;
      setStatus('mobileStatus', '', false);
      setStatus('emailStatus', '', false);
      updateIdentificationNoLabel();
    } else {
      alert(data.message || 'Registration failed');
    }
  } catch (error) {
    alert(error.message || 'Registration failed');
  }
});
