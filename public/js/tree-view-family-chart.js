(() => {
  'use strict';

  const BaseTreeView = window.FamilyTreeView;
  if (!BaseTreeView || !window.f3 || !window.d3) {
    console.error('Family Chart não foi carregado; o renderer legado será mantido.');
    return;
  }

  const legacyRender = BaseTreeView.prototype.render;
  const legacyFitVisible = BaseTreeView.prototype.fitVisible;
  const legacyCenterFocus = BaseTreeView.prototype.centerFocus;
  const legacyZoom = BaseTreeView.prototype.zoom;
  const legacyApplyTransform = BaseTreeView.prototype.applyTransform;
  const legacyStartPan = BaseTreeView.prototype.startPan;
  const legacyMovePan = BaseTreeView.prototype.movePan;
  const legacyEndPan = BaseTreeView.prototype.endPan;

  const CARD = { width: 242, height: 120 };

  BaseTreeView.prototype.familyChartData = function familyChartData() {
    const focusedId = String(this.focusId || '');
    const validIds = new Set(this.nodes.map((node) => String(node.id)));
    const relationIds = (ids, sourceId) => [...new Set((ids || []).map(String))]
      .filter((id) => id !== sourceId && validIds.has(id));
    const data = this.nodes.map((node) => {
      const id = String(node.id);
      return {
        id,
        data: {
          id,
          gender: this.genderClass(node.gender) === 'male' ? 'M' : (this.genderClass(node.gender) === 'female' ? 'F' : 'U'),
          name: node.name,
          shortName: node.shortName,
          dates: node.dates,
          birth: node.birth,
          death: node.death,
          birthPlace: node.birthPlace,
          photo: node.photo,
          status: node.status,
          person: node,
        },
        rels: {
          parents: relationIds(node.parents, id),
          spouses: relationIds(node.spouses, id),
          children: relationIds(node.children, id),
        },
      };
    });
    return data.sort((left, right) => (left.id === focusedId ? -1 : right.id === focusedId ? 1 : 0));
  };

  BaseTreeView.prototype.familyCardHtml = function familyCardHtml(treeDatum) {
    const payload = treeDatum.data.data;
    const node = payload.person || this.byId.get(String(treeDatum.data.id));
    if (!node) return '<div class="fc-person-card"></div>';
    const id = String(node.id);
    const former = id !== String(this.focusId) && this.isFormerUnion(this.unionBetween(id, this.focusId));
    const focus = id === String(this.focusId);
    const gender = this.genderClass(node.gender);
    const photo = String(node.photo || '');
    const validPhoto = photo && !photo.startsWith('data:') && /^(?:https?:\/\/|\/|\.\/|\.\.\/|uploads\/)/i.test(photo);
    const avatar = validPhoto
      ? `<img class="fc-person-avatar" src="${this.escape(photo)}" alt="">`
      : `<span class="fc-person-avatar fc-person-initials">${this.escape((node.shortName || node.name || '?').slice(0, 1).toUpperCase())}</span>`;
    const isReference = node.association === 'referenciada';
    const relation = isReference
      ? `Referenciada · ${node.sourceFamily || 'outro espaço'}`
      : (focus ? 'Em foco' : (former ? 'Ex-união' : 'Ramo familiar'));
    const edit = node.editavel
      ? `<button class="fc-card-edit" type="button" data-family-edit="${this.escape(id)}" aria-label="Editar ${this.escape(node.name)}">✎</button>`
      : '';
    const referenceBadge = isReference
      ? `<b class="fc-reference-badge" title="Pessoa referenciada de ${this.escape(node.sourceFamily || 'outro espaço')}">referenciada</b>`
      : '';
    return `<article class="fc-person-card fc-person-${gender}${focus ? ' is-focus' : ''}${former ? ' is-former' : ''}${isReference ? ' is-reference' : ''}${node.status === 'falecido' ? ' is-deceased' : ''}" data-person-id="${this.escape(id)}" title="${isReference ? `Origem: ${this.escape(node.sourceFamily || 'outro espaço')}` : ''}">
      <div class="fc-card-gender-band" aria-hidden="true"></div>
      ${avatar}
      <div class="fc-person-content">
        <h3>${this.escape(node.name)}</h3>
        <p>${this.escape(node.dates || 'Datas não informadas')}</p>
        <span>${this.escape(relation)}</span>
      </div>
      ${former ? '<b class="fc-former-badge" title="Ex-união">ex</b>' : ''}
      ${referenceBadge}
      ${edit}
    </article>`;
  };

  BaseTreeView.prototype.familyChartSort = function familyChartSort(left, right) {
    const a = this.byId.get(String(left.id || left.data?.id));
    const b = this.byId.get(String(right.id || right.data?.id));
    if (!a || !b) return 0;
    const dateValue = (node) => node.birth ? (Date.parse(node.birth) || Number.MAX_SAFE_INTEGER) : Number.MAX_SAFE_INTEGER;
    if (this.sortValue === 'nome_desc') return b.name.localeCompare(a.name, 'pt-BR');
    if (this.sortValue === 'nascimento_asc') return dateValue(a) - dateValue(b) || a.name.localeCompare(b.name, 'pt-BR');
    if (this.sortValue === 'nascimento_desc') return dateValue(b) - dateValue(a) || a.name.localeCompare(b.name, 'pt-BR');
    if (this.sortValue === 'atualizado_desc') return String(b.updated).localeCompare(String(a.updated)) || a.name.localeCompare(b.name, 'pt-BR');
    return a.name.localeCompare(b.name, 'pt-BR');
  };

  BaseTreeView.prototype.renderWithFamilyChart = function renderWithFamilyChart(options = {}) {
    if (!this.stage || !this.byId.size || !this.focusId) return;
    this.visibleIds = new Set(this.nodes.map((node) => String(node.id)));
    this.positions = new Map();
    this.stage.replaceChildren();
    this.stage.className = `tree-stage tree-stage-family-chart tree-mode-${this.modeValue} f3 f3-cont`;
    this.stage.setAttribute('aria-label', 'Visualização genealógica interativa');

    const ancestry = Math.max(1, Number(this.ancestorRange?.value || 2));
    const progeny = Math.max(1, Number(this.descendantRange?.value || 2));
    const maximumDepth = Math.max(ancestry, progeny);
    const focusScale = maximumDepth >= 5 ? 0.72 : (maximumDepth >= 4 ? 0.8 : (maximumDepth >= 3 ? 0.9 : 1));
    const chart = window.f3.createChart(this.stage, this.familyChartData());
    const card = chart.setCardHtml();
    const view = this;
    card
      .setCardDim({ width: CARD.width, height: CARD.height })
      .setCardInnerHtmlCreator((datum) => view.familyCardHtml(datum))
      .setOnCardClick((event, datum) => {
        const edit = event.target.closest?.('[data-family-edit]');
        if (edit) return;
        event.preventDefault();
        view.select(String(datum.data.id), { center: true });
      })
      .setOnCardUpdate(function onCardUpdate(datum) {
        const edit = this.querySelector('[data-family-edit]');
        if (!edit) return;
        edit.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          view.focusId = String(datum.data.id);
          view.updatePanel();
          view.openEditPanel();
        });
      });

    chart
      .setTransitionTime(260)
      .setOrientationVertical()
      .setCardXSpacing(292)
      .setCardYSpacing(this.modeValue === 'fan' ? 292 : 250)
      .setAncestryDepth(ancestry)
      .setProgenyDepth(progeny)
      .setShowSiblingsOfMain(this.modeValue === 'explorer')
      .setSortChildrenFunction((left, right) => view.familyChartSort(left, right))
      .setSortSpousesFunction((datum, data) => [...(datum.rels?.spouses || [])].sort((leftId, rightId) => {
        const left = data.find((item) => String(item.id) === String(leftId));
        const right = data.find((item) => String(item.id) === String(rightId));
        return view.familyChartSort(left || {}, right || {});
      }))
      .setLinkSpouseText((left, right) => view.isFormerUnion(view.unionBetween(left.data.id, right.data.id)) ? 'ex' : '')
      .setAfterUpdate(() => {
        const rendered = view.stage.querySelectorAll('.fc-person-card').length;
        view.setStatus(`${rendered} pessoas visíveis · modo ${view.modeLabel()} · Family Chart`);
        view.updateFamilyChartZoomLabel();
      });

    if (this.modeValue === 'fan') chart.setOrientationHorizontal();
    this.familyChart = chart;
    chart.updateMainId(String(this.focusId));
    chart.updateTree({
      initial: false,
      tree_position: 'main_to_middle',
      scale: focusScale,
      transition_time: 0,
    });
    this.updatePanel();
  };

  BaseTreeView.prototype.render = function render(options = {}) {
    return this.renderWithFamilyChart(options);
  };

  BaseTreeView.prototype.familyChartZoomTarget = function familyChartZoomTarget() {
    if (!this.familyChart?.svg) return null;
    const svg = this.familyChart.svg;
    const target = svg.__zoomObj ? svg : svg.parentNode;
    return target?.__zoomObj ? target : null;
  };

  BaseTreeView.prototype.updateFamilyChartZoomLabel = function updateFamilyChartZoomLabel() {
    const target = this.familyChartZoomTarget();
    if (!target || !this.zoomLabel) return;
    const scale = window.d3.zoomTransform(target).k;
    this.zoomLabel.textContent = `${Math.round(scale * 100)}%`;
  };

  BaseTreeView.prototype.zoom = function zoom(factor, clientX, clientY) {
    if (!this.familyChart) return legacyZoom.call(this, factor, clientX, clientY);
    if (clientX !== undefined || clientY !== undefined) return;
    const target = this.familyChartZoomTarget();
    if (!target) return;
    window.d3.select(target).call(target.__zoomObj.scaleBy, factor);
    this.updateFamilyChartZoomLabel();
  };

  BaseTreeView.prototype.fitVisible = function fitVisible() {
    if (!this.familyChart) return legacyFitVisible.call(this);
    this.familyChart.updateTree({ tree_position: 'fit', transition_time: 180 });
  };

  BaseTreeView.prototype.centerFocus = function centerFocus() {
    if (!this.familyChart) return legacyCenterFocus.call(this);
    this.familyChart.updateTree({ tree_position: 'main_to_middle', transition_time: 180 });
  };

  BaseTreeView.prototype.applyTransform = function applyTransform() {
    if (!this.familyChart) return legacyApplyTransform.call(this);
    this.updateFamilyChartZoomLabel();
  };

  BaseTreeView.prototype.startPan = function startPan(event) {
    if (!this.familyChart) return legacyStartPan.call(this, event);
  };
  BaseTreeView.prototype.movePan = function movePan(event) {
    if (!this.familyChart) return legacyMovePan.call(this, event);
  };
  BaseTreeView.prototype.endPan = function endPan() {
    if (!this.familyChart) return legacyEndPan.call(this);
  };
})();
