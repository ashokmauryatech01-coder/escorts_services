/* Velvet & Gold — shared behaviour. All server calls use the existing api.php actions. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const CFG = window.VG_CFG || {};

  const api = async (data) => {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...data, csrf: CFG.csrf })
    });
    let result = {};
    try { result = await res.json(); } catch (e) { /* non-JSON error */ }
    if (!res.ok) throw new Error(result.error || 'The request could not be completed.');
    return result;
  };

  let toastTimer;
  const toast = (message, bad = false) => {
    const el = $('#toast');
    if (!el) return;
    el.textContent = message;
    el.classList.toggle('bad', bad);
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 4200);
  };
  window.VG = { api, toast, cfg: CFG, $, $$ };

  /* Age gate */
  const gateBtn = $('#ageConfirm');
  if (gateBtn) {
    gateBtn.addEventListener('click', () => {
      try { sessionStorage.setItem('vg_age_confirmed', 'yes'); } catch (e) {}
      document.documentElement.classList.add('age-ok');
    });
  }

  /* Mobile nav */
  const navBtn = $('.nav-toggle'), navLinks = $('#navLinks');
  if (navBtn && navLinks) {
    const setNav = (open) => { navBtn.setAttribute('aria-expanded', open); navLinks.classList.toggle('open', open); };
    navBtn.addEventListener('click', () => setNav(navBtn.getAttribute('aria-expanded') !== 'true'));
    $$('a', navLinks).forEach(a => a.addEventListener('click', () => setNav(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setNav(false); });
  }

  /* Modals */
  let lastFocus = null;
  const openModal = (modal) => {
    lastFocus = document.activeElement;
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    const first = $('input:not([type=hidden]),select,textarea,button:not(.close)', modal);
    if (first) setTimeout(() => first.focus(), 30);
  };
  const closeModal = (modal) => {
    modal.classList.remove('open');
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  };
  $$('[data-open]').forEach(b => b.addEventListener('click', () => openModal(document.getElementById(b.dataset.open))));
  $$('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.closest('.modal'))));
  $$('.modal').forEach(m => m.addEventListener('mousedown', e => { if (e.target === m) closeModal(m); }));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') $$('.modal.open').forEach(closeModal); });

  /* Photos that fail to load fall back to the monogram behind them */
  $$('img.js-photo').forEach(img => {
    const hide = () => { img.style.display = 'none'; };
    img.addEventListener('error', hide);
    if (img.complete && img.naturalWidth === 0) hide();
  });

  /* Copy buttons */
  $$('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copy); toast('Copied to clipboard.'); }
    catch (e) { toast('Copy is not available in this browser.', true); }
  }));

  /* Booking request (directory + listing pages) */
  const bookingModal = $('#bookingModal');
  if (bookingModal) {
    $$('.book-button').forEach(button => button.addEventListener('click', () => {
      if (!CFG.isMember) {
        toast('Create a member profile to send a request.');
        setTimeout(() => { location.href = 'register.php'; }, 900);
        return;
      }
      if (CFG.upiOn && !CFG.paymentVerified) {
        toast('Your membership payment must be verified before you can send a request.');
        setTimeout(() => { location.href = 'payment.php'; }, 1200);
        return;
      }
      $('#bookingTitle').textContent = 'Request · ' + button.dataset.name;
      $('#bookingCompanionId').value = button.dataset.id;
      openModal(bookingModal);
    }));
    $('#bookingForm').addEventListener('submit', async event => {
      event.preventDefault();
      const form = new FormData(event.currentTarget);
      const submit = $('button[type=submit]', event.currentTarget);
      submit.disabled = true;
      try {
        await api({ action: 'book', companion_id: form.get('companion_id'), setting: form.get('setting'), date: form.get('date'), time: form.get('time'), duration: form.get('duration') });
        closeModal(bookingModal);
        toast('Request sent. The coordinator will confirm shortly.');
        setTimeout(() => { location.href = 'dashboard.php#requests'; }, 1400);
      } catch (error) { submit.disabled = false; toast(error.message, true); }
    });
  }

  /* Member sign out (uses logout.php; api.php's logout is coordinator-only) */
  $$('[data-member-logout]').forEach(b => b.addEventListener('click', async () => {
    try {
      const res = await fetch('logout.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf: CFG.csrf }) });
      if (!res.ok) throw new Error('Could not sign you out. Refresh and try again.');
      location.href = 'index.php';
    } catch (error) { toast(error.message, true); }
  }));

  /* Scroll-spy for dashboard side nav */
  const spyLinks = $$('[data-spy] a[href^="#"]');
  if (spyLinks.length && 'IntersectionObserver' in window) {
    const map = new Map(spyLinks.map(a => [a.getAttribute('href').slice(1), a]));
    const io = new IntersectionObserver(entries => {
      entries.forEach(en => { if (en.isIntersecting) { spyLinks.forEach(l => l.classList.remove('on')); map.get(en.target.id)?.classList.add('on'); } });
    }, { rootMargin: '-30% 0px -60% 0px' });
    map.forEach((_, id) => { const el = document.getElementById(id); if (el) io.observe(el); });
  }
})();
