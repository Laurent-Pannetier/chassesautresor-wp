/**
 * Génère le HTML des liens publics (ul ou placeholder).
 * Utilisable pour organisateur, chasse, etc.
 * @param {Array} liens - Tableau d’objets : [{ type_de_lien: 'facebook', url_lien: 'https://...' }]
 * @returns {string} HTML
 */
function renderLiensPublics(liens = []) {
  const icones = {
    site_web: 'fa-solid fa-globe',
    discord: 'fa-brands fa-discord',
    facebook: 'fa-brands fa-facebook-f',
    twitter: 'fa-brands fa-x-twitter',
    instagram: 'fa-brands fa-instagram'
  };

  const labels = {
    site_web: 'Site Web',
    discord: 'Discord',
    facebook: 'Facebook',
    twitter: 'Twitter/X',
    instagram: 'Instagram'
  };

  if (!Array.isArray(liens) || liens.length === 0) {
    return `
      <div class="liens-placeholder">
        <p class="liens-placeholder-message">Aucun lien ajouté pour le moment.</p>
        ${Object.entries(icones).map(([type, icone]) =>
          `<i class="fa ${icone} icone-grisee" title="${labels[type]}"></i>`
        ).join('')}
      </div>`;
  }

  return `
    <ul class="liste-liens-publics">
      ${liens.map(({ type_de_lien, url_lien }) => {
        const type = Array.isArray(type_de_lien) ? type_de_lien[0] : type_de_lien;
        const icone = icones[type] || 'fa-link';
        const label = labels[type] || type;
        const url = url_lien || '#';
        return `
          <li class="item-lien-public">
            <a href="${url}" class="lien-public lien-${type}" target="_blank" rel="noopener">
              <i class="fa ${icone}"></i>
              <span class="texte-lien">${label}</span>
            </a>
          </li>`;
      }).join('')}
    </ul>`;
}
window.renderLiensPublicsJS = renderLiensPublics;
