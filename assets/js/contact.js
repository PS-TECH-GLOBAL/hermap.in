'use strict';
(() => {
  const settings = document.getElementById('contact-settings');
  const form = document.getElementById('contact-form');
  if (!settings || !form) return;

  const base = settings.dataset.apiUrl;
  let challenge = null;
  let submitting = false;

  async function api(path, body) {
    const response = await fetch(base + path, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...(body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {})
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Please try again later.');
    return data;
  }

  async function newChallenge() {
    const btn = document.getElementById('refresh-captcha');
    const label = document.getElementById('captcha-question');
    const input = document.getElementById('contact-captcha');
    if (btn) btn.disabled = true;
    if (input) input.value = '';
    challenge = null;
    if (label) label.textContent = 'Loading human check…';
    try {
      const data = await api('challenge');
      challenge = data;
      if (label) label.textContent = `Human check: ${data.question}`;
    } catch (e) {
      if (label) label.textContent = e.message;
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  document.getElementById('refresh-captcha')?.addEventListener('click', newChallenge);
  newChallenge();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (submitting) return;
    const result = document.getElementById('contact-result');
    const btn = document.getElementById('submit-contact');
    const ticketBox = document.getElementById('contact-ticket');
    const ticketNum = document.getElementById('ticket-number');

    if (!challenge) {
      if (result) result.textContent = 'Please refresh the human check.';
      return;
    }

    submitting = true;
    if (btn) btn.disabled = true;
    if (result) result.textContent = 'Sending your message…';

    try {
      const payload = {
        csrf: challenge.csrf,
        challenge_id: challenge.id,
        challenge_answer: document.getElementById('contact-captcha')?.value || '',
        website_check: document.getElementById('website-check')?.value || '',
        category: document.getElementById('contact-category')?.value || 'general',
        name: document.getElementById('contact-name')?.value || '',
        email: document.getElementById('contact-email')?.value || '',
        subject: document.getElementById('contact-subject')?.value || '',
        message: document.getElementById('contact-message')?.value || '',
      };

      const data = await api('contact', payload);
      form.hidden = true;
      if (ticketBox) ticketBox.hidden = false;
      if (ticketNum) ticketNum.textContent = data.ticket_id;
      if (result) result.textContent = data.message;
    } catch (err) {
      if (result) result.textContent = err.message;
      await newChallenge();
    } finally {
      submitting = false;
      if (btn) btn.disabled = false;
    }
  });
})();
