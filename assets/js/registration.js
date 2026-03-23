const form = document.getElementById('registrationForm');
const appBox = document.getElementById('appBox');
const verificationState = { mobile: false, email: false };

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

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!verificationState.mobile || !verificationState.email) {
    alert('Please verify both mobile number and email before submitting.');
    return;
  }

  const formData = Object.fromEntries(new FormData(form).entries());
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
