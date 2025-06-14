document.addEventListener('DOMContentLoaded', () => {
  const morseMap = {
    a: '.-',    b: '-...',  c: '-.-.', d: '-..',   e: '.',
    f: '..-.',  g: '--.',   h: '....', i: '..',    j: '.---',
    k: '-.-',   l: '.-..',  m: '--',   n: '-.',    o: '---',
    p: '.--.',  q: '--.-',  r: '.-.',  s: '...',   t: '-',
    u: '..-',   v: '...-',  w: '.--',  x: '-..-',  y: '-.--',
    z: '--..',  ' ': '   '
  };

  function normaliserCaracteres(texte) {
    return texte
      .normalize("NFD")                     // Décompose les accents (é → e + ́)
      .replace(/[\u0300-\u036f]/g, '')     // Supprime les diacritiques
      .replace(/œ/g, 'oe')
      .replace(/æ/g, 'ae')
      .replace(/ç/g, 'c')
      .replace(/[^a-z ]/gi, ' ');          // Remplace tout sauf lettres et espaces par un espace
  }

  function texteEnMorse(texte) {
    return normaliserCaracteres(texte)
      .toLowerCase()
      .split('')
      .map(char => morseMap[char] || '')
      .join(' ');
  }

  function afficherMorseDansWrapper(texte, wrapper) {
    if (!wrapper) return;
    wrapper.innerHTML = ''; // Nettoie l'ancien rendu

    const morse = texteEnMorse(texte);
    for (const symbole of morse) {
      if (symbole === '.') {
        const point = document.createElement('span');
        point.className = 'point';
        wrapper.appendChild(point);
      } else if (symbole === '-') {
        const tiret = document.createElement('span');
        tiret.className = 'tiret';
        wrapper.appendChild(tiret);
      } else if (symbole === ' ') {
        const espace = document.createElement('span');
        espace.style.width = '10px';
        espace.style.display = 'inline-block';
        wrapper.appendChild(espace);
      }
    }
  }

  // Initialisation au chargement
  const wrapper = document.querySelector('.morse-wrapper');
  const nom = wrapper?.dataset.morse?.trim();
  if (wrapper && nom) {
    afficherMorseDansWrapper(nom, wrapper);
  }

  // Dynamique : si le titre change via JS
  const titre = document.querySelector('[data-champ="post_title"] .champ-input');
  if (titre) {
    titre.addEventListener('input', () => {
      afficherMorseDansWrapper(titre.value, wrapper);
    });
  }
});
