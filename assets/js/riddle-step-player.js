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
    return nextArticle;
  }

  const finalForm = parsed.querySelector('.formulaire-reponse-auto, .formulaire-reponse-manuelle');
  if (!finalForm) return emptyCompletedStep ? player : currentArticle;
  player.insertAdjacentElement('afterend', finalForm);
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
      form.querySelector('.riddle-directions__sequence')?.replaceChildren();
      form.querySelector('.riddle-colors__sequence')?.replaceChildren();
      form.querySelector('.riddle-numbers__sequence')?.replaceChildren();
      form.querySelector('.riddle-safe__sequence')?.replaceChildren();
      form.querySelectorAll('[class$="__sequence"]').forEach(output => setWidgetSequenceLabel(output));
      const safeDial = form.querySelector('.riddle-safe');
      if (safeDial) {
        safeDial.dataset.value = '0';
        safeDial.style.setProperty('--safe-angle', '0deg');
        safeDial.setAttribute('aria-valuenow', '0');
        safeDial.setAttribute('aria-valuetext', `${RiddleStepPlayer.safeValueLabel}: 0`);
        safeDial.querySelector('.riddle-safe__value').textContent = '0';
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

const setSafeDialValue = (dial, value) => {
  const normalized = (value + 100) % 100;
  dial.dataset.value = normalized;
  dial.style.setProperty('--safe-angle', `${normalized * 3.6}deg`);
  dial.setAttribute('aria-valuenow', normalized);
  dial.setAttribute('aria-valuetext', `${RiddleStepPlayer.safeValueLabel}: ${normalized}`);
  dial.querySelector('.riddle-safe__value').textContent = normalized;
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
  dial.dataset.startValue = dial.dataset.value || '0';
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
  setSafeDialValue(dial, Math.round(Number(dial.dataset.startValue) + rotation / 3.6));
});

const finishSafeDialPointer = (event, shouldCommit) => {
  const dial = event.target.closest('.riddle-safe');
  if (!dial || Number(dial.dataset.pointerId) !== event.pointerId) return;
  const rotation = Number(dial.dataset.rotation);
  const startValue = Number(dial.dataset.startValue || 0);
  delete dial.dataset.pointerId;
  delete dial.dataset.previousAngle;
  delete dial.dataset.rotation;
  delete dial.dataset.startValue;
  if (!shouldCommit) {
    setSafeDialValue(dial, startValue);
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
  setSafeDialValue(dial, Number(dial.dataset.value || 0) + (direction === 'H' ? 1 : -1));
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
  setSafeDialValue(form.querySelector('.riddle-safe'), 0);
});
