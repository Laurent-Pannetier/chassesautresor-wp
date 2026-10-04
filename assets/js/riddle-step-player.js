const waitForImage = image => {
  if (image.complete) return image.decode?.().catch(() => {}) || Promise.resolve();

  return new Promise(resolve => {
    const timeout = window.setTimeout(resolve, 3000);
    const finish = () => {
      window.clearTimeout(timeout);
      resolve();
    };
    image.addEventListener('load', finish, { once: true });
    image.addEventListener('error', finish, { once: true });
  });
};

const setWidgetSequenceLabel = (output, values = []) => {
  if (!output) return;
  const value = values.length ? values.join(', ') : RiddleStepPlayer.emptySequenceLabel;
  output.setAttribute('aria-label', `${RiddleStepPlayer.sequenceLabel}: ${value}`);
};

const pianoFrequencies = {
  C1: 261.63, 'C#1': 277.18, D1: 293.66, 'D#1': 311.13, E1: 329.63, F1: 349.23,
  'F#1': 369.99, G1: 392, 'G#1': 415.3, A1: 440, 'A#1': 466.16, B1: 493.88,
  C2: 523.25, 'C#2': 554.37, D2: 587.33, 'D#2': 622.25, E2: 659.25, F2: 698.46,
  'F#2': 739.99, G2: 783.99, 'G#2': 830.61, A2: 880, 'A#2': 932.33, B2: 987.77
};

let pianoAudioContext;
const playPianoNote = (note, delay = 0, key = null) => {
  const AudioContext = window.AudioContext || window.webkitAudioContext;
  if (!AudioContext || !pianoFrequencies[note]) return;
  pianoAudioContext ||= new AudioContext();
  if (pianoAudioContext.state === 'suspended') {
    pianoAudioContext.resume().catch(() => {});
  }
  const start = pianoAudioContext.currentTime + delay;
  const oscillator = pianoAudioContext.createOscillator();
  const gain = pianoAudioContext.createGain();
  oscillator.type = 'triangle';
  oscillator.frequency.value = pianoFrequencies[note];
  gain.gain.setValueAtTime(0.0001, start);
  gain.gain.exponentialRampToValueAtTime(0.35, start + 0.015);
  gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.55);
  oscillator.connect(gain).connect(pianoAudioContext.destination);
  oscillator.start(start);
  oscillator.stop(start + 0.56);
  if (!key) return;
  const highlightDelay = Math.max(0, delay * 1000);
  window.setTimeout(() => {
    key.classList.add('is-playing');
    window.setTimeout(() => key.classList.remove('is-playing'), 280);
  }, highlightDelay);
};

const initializeGpsWidgets = root => {
  if (typeof window.L === 'undefined' || !root) return;
  const forms = [];
  if (root.matches?.('.riddle-step-gps-form:not([data-map-ready])')) {
    forms.push(root);
  }
  root.querySelectorAll?.('.riddle-step-gps-form:not([data-map-ready])').forEach(form => forms.push(form));
  forms.forEach(form => {
    const container = form.querySelector('.riddle-gps__map');
    const latitude = form.querySelector('.riddle-gps__latitude');
    const longitude = form.querySelector('.riddle-gps__longitude');
    const answer = form.querySelector('input[name="reponse"]');
    const submit = form.querySelector('button[type="submit"]');
    const map = window.L.map(container, { center: [20, 0], zoom: 2 });
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19
    }).addTo(map);
    let marker;

    const setCoordinates = (lat, lng, pan = false) => {
      if (!Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) return;
      const point = window.L.latLng(lat, lng);
      if (!marker) {
        marker = window.L.marker(point, { draggable: true }).addTo(map);
        marker.on('dragend', () => setCoordinates(marker.getLatLng().lat, marker.getLatLng().lng));
      } else {
        marker.setLatLng(point);
      }
      latitude.value = point.lat.toFixed(6);
      longitude.value = point.lng.toFixed(6);
      answer.value = `${latitude.value} ${longitude.value}`;
      submit.disabled = false;
      if (pan) map.setView(point, Math.max(map.getZoom(), 12));
    };

    map.on('click', event => setCoordinates(event.latlng.lat, event.latlng.lng));
    [latitude, longitude].forEach(input => input.addEventListener('input', () => {
      setCoordinates(Number.parseFloat(latitude.value), Number.parseFloat(longitude.value), true);
    }));
    form.querySelector('.riddle-gps-reset').addEventListener('click', () => {
      if (marker) marker.remove();
      marker = undefined;
      latitude.value = '';
      longitude.value = '';
      answer.value = '';
      submit.disabled = true;
      map.setView([20, 0], 2);
      latitude.focus();
    });
    form.dataset.mapReady = '1';
    window.setTimeout(() => map.invalidateSize(), 0);
  });
};

const hasMeaningfulStepContent = article => {
  if (article.querySelector('.riddle-player-step__image')) return true;
  const content = article.querySelector('.riddle-player-step__content');
  if (!content) return false;
  if (content.querySelector('img, picture, video, audio, iframe, canvas, svg')) return true;
  return content.textContent.replace(/\u00a0/g, ' ').trim() !== '';
};

const positionRiddleStepTarget = async target => {
  const element = target === 'final'
    ? document.querySelector('.formulaire-reponse-auto, .formulaire-reponse-manuelle') ||
      [...document.querySelectorAll('.riddle-player-step')].pop()
    : document.querySelector(`[data-player-step-id="${target}"]`);
  if (!element) return;

  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const position = behavior => element.scrollIntoView({ behavior, block: 'start' });
  position(reducedMotion ? 'auto' : 'smooth');

  const precedingImages = [...document.querySelectorAll('.riddle-steps-player img')]
    .filter(image => image.compareDocumentPosition(element) & Node.DOCUMENT_POSITION_FOLLOWING);
  await Promise.allSettled(precedingImages.map(waitForImage));
  await Promise.race([
    document.fonts?.ready || Promise.resolve(),
    new Promise(resolve => window.setTimeout(resolve, 3000))
  ]);
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => position('auto')));
};

const unlockRiddleStepContent = (form, data) => {
  if (!data.response_html) return null;
  const parsed = new DOMParser().parseFromString(data.response_html, 'text/html');
  const currentArticle = form.closest('.riddle-player-step');
  const player = currentArticle?.closest('.riddle-steps-player');
  if (!currentArticle || !player) return null;

  form.remove();
  currentArticle.classList.remove('is-current');
  currentArticle.classList.add('is-completed');
  const emptyCompletedStep = !hasMeaningfulStepContent(currentArticle);
  if (emptyCompletedStep) currentArticle.remove();

  if (data.current_step_id) {
    const selector = `[data-player-step-id="${data.current_step_id}"]`;
    const nextArticle = parsed.querySelector(selector);
    if (!nextArticle || player.querySelector(selector)) return null;
    player.append(nextArticle);
    initializeGpsWidgets(nextArticle);
    return nextArticle;
  }

  const finalForm = parsed.querySelector('.formulaire-reponse-auto, .formulaire-reponse-manuelle');
  if (!finalForm) return emptyCompletedStep ? player : currentArticle;
  player.insertAdjacentElement('afterend', finalForm);
  initializeGpsWidgets(finalForm);
  const manualFeedback = parsed.querySelector('.formulaire-reponse-manuelle + .reponse-feedback');
  if (manualFeedback) finalForm.insertAdjacentElement('afterend', manualFeedback);
  document.dispatchEvent(new CustomEvent('riddle-step-content-updated'));
  return finalForm;
};

const focusUnlockedContent = target => {
  if (!target) return;
  if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
  target.focus({ preventScroll: true });
};

document.addEventListener('DOMContentLoaded', async () => {
  initializeGpsWidgets(document);
  const target = window.sessionStorage.getItem('riddleStepScrollTarget');
  if (!target) return;
  window.history.scrollRestoration = 'manual';
  window.sessionStorage.removeItem('riddleStepScrollTarget');
  await positionRiddleStepTarget(target);
});

document.addEventListener('submit', async event => {
  const form = event.target.closest('.riddle-step-click-form, .riddle-step-text-form');
  if (!form || typeof RiddleStepPlayer === 'undefined') return;
  event.preventDefault();
  const button = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('.riddle-step-click-form__feedback');
  const data = new FormData(form);
  data.append('action', form.dataset.widgetAction);
  form.setAttribute('aria-busy', 'true');
  feedback.setAttribute('role', 'status');
  feedback.textContent = '';
  button.disabled = true;
  let keepDisabled = false;
  try {
    const response = await fetch(RiddleStepPlayer.ajaxUrl, { method: 'POST', body: data });
    let result;
    try {
      result = await response.json();
    } catch (error) {
      throw new Error(RiddleStepPlayer.error);
    }
    if (!result || typeof result !== 'object') throw new Error(RiddleStepPlayer.error);
    if (!result.success && result.data?.blocked) {
      window.RiddleRetryCountdown?.apply(form, result.data);
      keepDisabled = true;
    }
    if (!result.success) throw new Error(result.data?.message || RiddleStepPlayer.error);
    window.RiddleRetryCountdown?.apply(form, result.data.retry);
    if (result.data.resultat && result.data.resultat !== 'bon') {
      feedback.textContent = result.data.resultat === 'variante' && result.data.message
        ? result.data.message
        : RiddleStepPlayer.wrong;
      const answerInput = form.querySelector('input[name="reponse"]');
      if (answerInput) answerInput.value = '';
      form.querySelector('.riddle-gps-reset')?.click();
      if (form.classList.contains('riddle-step-piano-form')) form.querySelector('.riddle-widget-reset')?.click();
      form.querySelector('.riddle-directions__sequence')?.replaceChildren();
      form.querySelector('.riddle-colors__sequence')?.replaceChildren();
      form.querySelector('.riddle-numbers__sequence')?.replaceChildren();
      form.querySelector('.riddle-safe__sequence')?.replaceChildren();
      form.querySelectorAll('[class$="__sequence"]').forEach(output => setWidgetSequenceLabel(output));
      const safeDial = form.querySelector('.riddle-safe');
      if (safeDial) {
        setSafeDialAngle(safeDial, 0);
        safeDial.querySelector('.riddle-safe__direction')?.replaceChildren('*');
        delete safeDial.dataset.direction;
      }
      return;
    }
    const target = unlockRiddleStepContent(form, result.data);
    if (!target) throw new Error(RiddleStepPlayer.error);
    focusUnlockedContent(target);
    target.scrollIntoView({
      behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
      block: 'start'
    });
  } catch (error) {
    feedback.setAttribute('role', 'alert');
    feedback.textContent = error instanceof Error && error.message
      ? error.message
      : RiddleStepPlayer.error;
  } finally {
    if (form.isConnected) {
      form.setAttribute('aria-busy', 'false');
      button.disabled = keepDisabled;
    }
  }
});

document.addEventListener('click', event => {
  const form = event.target.closest('.riddle-step-piano-form');
  if (!form) return;
  const input = form.querySelector('input[name="reponse"]');
  const output = form.querySelector('.riddle-piano__sequence');
  const playButton = form.querySelector('.riddle-piano__play');
  const submitButton = form.querySelector('button[type="submit"]');
  if (event.target.closest('.riddle-widget-reset')) {
    input.value = '';
    output.textContent = '';
    playButton.disabled = true;
    submitButton.disabled = true;
    setWidgetSequenceLabel(output);
    return;
  }
  if (event.target.closest('.riddle-piano__play')) {
    input.value.split(',').filter(Boolean).forEach((note, index) => {
      const key = form.querySelector(`.riddle-piano__key[data-note="${note}"]`);
      playPianoNote(note, index * 0.45, key);
    });
    return;
  }
  const key = event.target.closest('.riddle-piano__key');
  if (!key) return;
  const sequence = input.value ? input.value.split(',') : [];
  sequence.push(key.dataset.note);
  input.value = sequence.join(',');
  output.textContent = sequence.join(' ');
  playButton.disabled = false;
  submitButton.disabled = false;
  setWidgetSequenceLabel(output, sequence);
  playPianoNote(key.dataset.note, 0, key);
});

document.addEventListener('click', event => {
  const form = event.target.closest('.riddle-step-directions-form');
  if (!form) return;
  const input = form.querySelector('input[name="reponse"]');
  const output = form.querySelector('.riddle-directions__sequence');
  if (event.target.closest('.riddle-directions-reset')) {
    input.value = '';
    output.textContent = '';
    setWidgetSequenceLabel(output);
    return;
  }
  const button = event.target.closest('.riddle-direction');
  if (!button) return;
  const sequence = input.value ? input.value.split(',') : [];
  sequence.push(button.dataset.direction);
  input.value = sequence.join(',');
  const symbols = { NW: '↖', N: '↑', NE: '↗', W: '←', E: '→', SW: '↙', S: '↓', SE: '↘' };
  output.textContent = sequence.map(direction => symbols[direction]).join(' ');
  setWidgetSequenceLabel(output, sequence.map(direction => button.closest('form')
    .querySelector(`[data-direction="${direction}"]`)?.dataset.label || direction));
});

document.addEventListener('click', event => {
  const form = event.target.closest('.riddle-step-colors-form');
  if (!form) return;
  const input = form.querySelector('input[name="reponse"]');
  const output = form.querySelector('.riddle-colors__sequence');
  if (event.target.closest('.riddle-colors-reset')) {
    input.value = '';
    output.replaceChildren();
    setWidgetSequenceLabel(output);
    return;
  }
  const button = event.target.closest('.riddle-color');
  if (!button) return;
  const sequence = input.value ? input.value.split(',') : [];
  sequence.push(button.dataset.color);
  input.value = sequence.join(',');
  const dot = document.createElement('span');
  dot.className = `riddle-color-dot riddle-color--${button.dataset.color}`;
  dot.setAttribute('aria-hidden', 'true');
  output.append(dot);
  setWidgetSequenceLabel(output, sequence.map(color => form
    .querySelector(`[data-color="${color}"]`)?.dataset.label || color));
});

document.addEventListener('click', event => {
  const form = event.target.closest('.riddle-step-numbers-form');
  if (!form) return;
  const input = form.querySelector('input[name="reponse"]');
  const output = form.querySelector('.riddle-numbers__sequence');
  if (event.target.closest('.riddle-widget-reset')) {
    input.value = '';
    output.textContent = '';
    setWidgetSequenceLabel(output);
    return;
  }
  const button = event.target.closest('.riddle-number');
  if (!button) return;
  input.value += button.dataset.number;
  const digit = document.createElement('span');
  digit.textContent = button.dataset.number;
  output.append(digit);
  setWidgetSequenceLabel(output, input.value.split(''));
});

const safeDialAngle = (dial, event) => {
  const bounds = dial.getBoundingClientRect();
  const x = event.clientX - (bounds.left + bounds.width / 2);
  const y = event.clientY - (bounds.top + bounds.height / 2);
  return (Math.atan2(y, x) * 180 / Math.PI + 90 + 360) % 360;
};

const setSafeDialAngle = (dial, angle) => {
  const displayAngle = Math.round(angle * 10) / 10;
  const markerValue = Math.round(-displayAngle / 3.6);
  const normalized = ((markerValue % 100) + 100) % 100;
  dial.dataset.value = normalized;
  dial.dataset.angle = displayAngle;
  dial.style.setProperty('--safe-angle', `${displayAngle}deg`);
  dial.setAttribute('aria-valuenow', normalized);
  dial.setAttribute('aria-valuetext', `${RiddleStepPlayer.safeValueLabel}: ${normalized}`);
  dial.querySelector('.riddle-safe__value').textContent = normalized;
};

const setSafeDialDirection = (dial, direction = '') => {
  const directionOutput = dial.querySelector('.riddle-safe__direction');
  const symbol = direction === 'H' ? '↷' : direction === 'A' ? '↶' : '*';
  if (directionOutput) directionOutput.textContent = symbol;
  if (direction) {
    dial.dataset.direction = direction;
  } else {
    delete dial.dataset.direction;
  }
};

const commitSafeDialMovement = (form, direction, value) => {
  if (!direction) return;
  const input = form.querySelector('input[name="reponse"]');
  const output = form.querySelector('.riddle-safe__sequence');
  const movement = `${direction}${value}`;
  const sequence = input.value ? input.value.split(',') : [];
  sequence.push(movement);
  input.value = sequence.join(',');
  output.textContent = sequence.map(item => `${item.startsWith('H') ? '↻' : '↺'} ${item.slice(1)}`).join('  ');
  setWidgetSequenceLabel(output, sequence.map(item => {
    const direction = item.startsWith('H')
      ? RiddleStepPlayer.clockwiseLabel
      : RiddleStepPlayer.counterclockwiseLabel;
    return `${direction} ${item.slice(1)}`;
  }));
};

document.addEventListener('pointerdown', event => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial) return;
  event.preventDefault();
  dial.setPointerCapture(event.pointerId);
  dial.dataset.pointerId = event.pointerId;
  dial.dataset.previousAngle = safeDialAngle(dial, event);
  dial.dataset.rotation = '0';
  dial.dataset.startAngle = dial.dataset.angle || String(-Number(dial.dataset.value || 0) * 3.6);
  dial.dataset.startDirection = dial.dataset.direction || '';
});

document.addEventListener('pointermove', event => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial || Number(dial.dataset.pointerId) !== event.pointerId) return;
  const angle = safeDialAngle(dial, event);
  const previous = Number(dial.dataset.previousAngle);
  let delta = angle - previous;
  if (delta > 180) delta -= 360;
  if (delta < -180) delta += 360;
  const rotation = Number(dial.dataset.rotation) + delta;
  dial.dataset.previousAngle = angle;
  dial.dataset.rotation = rotation;
  if (rotation !== 0) setSafeDialDirection(dial, rotation > 0 ? 'H' : 'A');
  const steps = Math.round(rotation / 3.6);
  setSafeDialAngle(dial, Number(dial.dataset.startAngle) + steps * 3.6);
});

const finishSafeDialPointer = (event, shouldCommit) => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial || Number(dial.dataset.pointerId) !== event.pointerId) return;
  const rotation = Number(dial.dataset.rotation);
  const startAngle = Number(dial.dataset.startAngle || 0);
  const startDirection = dial.dataset.startDirection || '';
  delete dial.dataset.pointerId;
  delete dial.dataset.previousAngle;
  delete dial.dataset.rotation;
  delete dial.dataset.startAngle;
  delete dial.dataset.startDirection;
  if (!shouldCommit) {
    setSafeDialAngle(dial, startAngle);
    setSafeDialDirection(dial, startDirection);
    return;
  }
  if (Math.abs(rotation) < 1.8) return;
  commitSafeDialMovement(
    dial.closest('.riddle-step-safe_dial-form'),
    rotation > 0 ? 'H' : 'A',
    Number(dial.dataset.value || 0)
  );
};

document.addEventListener('pointerup', event => finishSafeDialPointer(event, true));
document.addEventListener('pointercancel', event => finishSafeDialPointer(event, false));

document.addEventListener('keydown', event => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
  event.preventDefault();
  const direction = event.key === 'ArrowRight' ? 'H' : 'A';
  const increment = direction === 'H' ? 1 : -1;
  setSafeDialDirection(dial, direction);
  const currentAngle = Number(dial.dataset.angle ?? -Number(dial.dataset.value || 0) * 3.6);
  setSafeDialAngle(dial, currentAngle + increment * 3.6);
  dial.dataset.keyboardDirection = direction;
});

document.addEventListener('keyup', event => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
  commitSafeDialMovement(
    dial.closest('.riddle-step-safe_dial-form'),
    dial.dataset.keyboardDirection,
    Number(dial.dataset.value || 0)
  );
  delete dial.dataset.keyboardDirection;
});

document.addEventListener('click', event => {
  const form = event.target.closest('.riddle-step-safe_dial-form');
  if (!form || !event.target.closest('.riddle-widget-reset')) return;
  form.querySelector('input[name="reponse"]').value = '';
  form.querySelector('.riddle-safe__sequence').textContent = '';
  setWidgetSequenceLabel(form.querySelector('.riddle-safe__sequence'));
  const dial = form.querySelector('.riddle-safe');
  setSafeDialAngle(dial, 0);
  setSafeDialDirection(dial);
});
