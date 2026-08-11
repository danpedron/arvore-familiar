(() => {
  'use strict';

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const CARD = { width: 260, height: 126 };
  const LAYOUT = { gapX: 34, rowHeight: 245, paddingX: 120, paddingY: 110 };

  class FamilyTreeView {
    constructor(options = {}) {
      this.viewport = document.querySelector(options.viewport);
      this.stage = document.querySelector(options.stage);
      this.panel = document.querySelector(options.panel);
      this.search = document.querySelector(options.search);
      this.results = document.querySelector(options.results);
      this.ancestorRange = document.querySelector(options.ancestorRange);
      this.descendantRange = document.querySelector(options.descendantRange);
      this.status = document.querySelector(options.status);
      this.zoomLabel = document.querySelector(options.zoomLabel);
      this.empty = document.querySelector(options.empty);
      this.nodes = [];
      this.byId = new Map();
      this.positions = new Map();
      this.visibleIds = new Set();
      this.focusId = null;
      this.graph = null;
      this.transform = { x: 0, y: 0, scale: 1 };
      this.pointer = null;
      this.firstRender = true;
      this.panelOpen = true;

      if (!this.viewport || !this.stage) throw new Error('A área da árvore não foi encontrada.');
      this.createWorld();
      this.bindControls();
    }

    createWorld() {
      this.stage.replaceChildren();
      this.stage.className = 'tree-stage';
      this.world = document.createElement('div');
      this.world.className = 'tree-world';
      this.links = document.createElementNS(SVG_NS, 'svg');
      this.links.classList.add('tree-links');
      this.cards = document.createElement('div');
      this.cards.className = 'tree-cards';
      this.world.append(this.links, this.cards);
      this.stage.append(this.world);
    }

    async load() {
      this.setStatus('Carregando dados da família…');
      try {
        const response = await fetch('arvore_dados.php', {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        if (!response.ok) throw new Error(`Resposta HTTP ${response.status}`);
        const payload = await response.json();
        this.ingest(payload);
        if (!this.nodes.length) {
          this.showEmpty();
          return;
        }
        this.hideEmpty();
        const requested = new URLSearchParams(window.location.search).get('foco');
        this.focusId = this.byId.has(String(requested)) ? String(requested) : this.nodes[0].id;
        this.render({ fit: true });
        this.updatePanel();
      } catch (error) {
        console.error(error);
        this.showEmpty('Não foi possível carregar a árvore', 'A sessão pode ter expirado ou o servidor não respondeu. Recarregue a página para tentar novamente.');
      }
    }

    ingest(payload) {
      const source = Array.isArray(payload?.pessoas) ? payload.pessoas : [];
      this.nodes = source.map((raw) => {
        const data = raw.data || raw;
        const rels = raw.rels || raw.relations || raw;
        const name = String(data.nome || data.nome_completo || 'Pessoa sem nome').trim();
        return {
          id: String(raw.id),
          name,
          shortName: String(data.nomeCurto || name.split(/\s+/)[0] || name),
          gender: String(data.gender || data.sexo || 'neutral').toLowerCase(),
          dates: String(data.datas || this.formatDates(data) || 'Datas não informadas'),
          birthPlace: String(data.localNascimento || data.local_nascimento || ''),
          photo: String(data.avatar || data.foto_perfil || data.foto || ''),
          parents: this.ids(rels.parents || raw.pais),
          children: this.ids(rels.children || raw.filhos),
          spouses: this.ids(rels.spouses || raw.conjuges),
          unions: Array.isArray(data.unioes) ? data.unioes : [],
          deceased: data.status === 'falecido' || Boolean(data.falecido),
        };
      });
      this.byId = new Map(this.nodes.map((node) => [node.id, node]));
      this.family = payload?.familia || {};
      this.totals = payload?.totais || {};
      const total = Number(this.totals.pessoas || this.nodes.length);
      const count = document.querySelector('[data-tree-total]');
      if (count) count.textContent = `${total} pessoas`;
    }

    ids(value) {
      return Array.isArray(value) ? value.map((id) => String(id)) : [];
    }

    formatDates(data) {
      const birth = data.nascimento || data.data_nascimento;
      const death = data.falecimento || data.data_falecimento;
      if (!birth && !death) return '';
      const year = (date) => date ? String(date).slice(0, 4) : '?';
      return `${year(birth)} — ${year(death)}`;
    }

    bindControls() {
      document.querySelectorAll('[data-tree-action]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.dataset.treeAction;
          if (action === 'zoom-in') this.zoom(1.18);
          if (action === 'zoom-out') this.zoom(0.84);
          if (action === 'fit') this.fitVisible();
          if (action === 'center') this.centerFocus();
          if (action === 'toggle-panel') this.togglePanel();
        });
      });

      this.search?.addEventListener('input', () => this.updateSearch());
      this.search?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          this.search.value = '';
          this.updateSearch();
          this.search.blur();
        }
        if (event.key === 'Enter') {
          const first = this.results?.querySelector('[data-search-id]');
          if (first) this.select(first.dataset.searchId);
        }
      });

      this.ancestorRange?.addEventListener('input', () => this.render({ center: true }));
      this.descendantRange?.addEventListener('input', () => this.render({ center: true }));

      this.viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        this.zoom(event.deltaY < 0 ? 1.1 : 0.9, event.clientX, event.clientY);
      }, { passive: false });

      this.viewport.addEventListener('pointerdown', (event) => {
        if (event.target.closest('.tree-card')) return;
        this.pointer = {
          id: event.pointerId,
          x: event.clientX,
          y: event.clientY,
          startX: this.transform.x,
          startY: this.transform.y,
        };
        this.viewport.setPointerCapture(event.pointerId);
        this.viewport.classList.add('is-panning');
      });
      this.viewport.addEventListener('pointermove', (event) => {
        if (!this.pointer || this.pointer.id !== event.pointerId) return;
        this.transform.x = this.pointer.startX + event.clientX - this.pointer.x;
        this.transform.y = this.pointer.startY + event.clientY - this.pointer.y;
        this.applyTransform();
      });
      const stopPan = (event) => {
        if (!this.pointer || this.pointer.id !== event.pointerId) return;
        this.pointer = null;
        this.viewport.releasePointerCapture?.(event.pointerId);
        this.viewport.classList.remove('is-panning');
      };
      this.viewport.addEventListener('pointerup', stopPan);
      this.viewport.addEventListener('pointercancel', stopPan);
      this.viewport.addEventListener('dblclick', (event) => {
        if (!event.target.closest('.tree-card')) this.fitVisible();
      });
      window.addEventListener('resize', () => {
        if (this.graph) this.fitVisible();
      });
    }

    buildGraph() {
      const up = Number(this.ancestorRange?.value || 3);
      const down = Number(this.descendantRange?.value || 3);
      const focus = this.byId.get(String(this.focusId));
      if (!focus) return { ids: new Set(), generations: new Map(), order: [] };

      const ids = new Set([focus.id]);
      const generations = new Map([[focus.id, 0]]);
      const order = [focus.id];
      const queue = [{ id: focus.id, generation: 0, expand: true }];

      while (queue.length) {
        const current = queue.shift();
        const node = this.byId.get(current.id);
        if (!node) continue;
        const related = [];
        if (current.expand && current.generation <= 0 && Math.abs(current.generation) < up) {
          node.parents.forEach((id) => related.push({ id, generation: current.generation - 1, expand: true }));
        }
        if (current.expand && current.generation >= 0 && current.generation < down) {
          node.children.forEach((id) => related.push({ id, generation: current.generation + 1, expand: true }));
        }
        if (current.generation === 0) {
          node.spouses.forEach((id) => related.push({ id, generation: 0, expand: false }));
        }
        related.forEach((item) => {
          if (!this.byId.has(item.id) || ids.has(item.id)) return;
          ids.add(item.id);
          generations.set(item.id, item.generation);
          order.push(item.id);
          queue.push(item);
        });
      }
      return { ids, generations, order };
    }

    render(options = {}) {
      this.graph = this.buildGraph();
      this.visibleIds = this.graph.ids;
      this.positions.clear();
      const rows = new Map();
      this.graph.ids.forEach((id) => {
        const generation = this.graph.generations.get(id) ?? 0;
        if (!rows.has(generation)) rows.set(generation, []);
        rows.get(generation).push(id);
      });

      const orderIndex = new Map(this.graph.order.map((id, index) => [id, index]));
      rows.forEach((ids) => ids.sort((a, b) => {
        if (a === this.focusId) return -1;
        if (b === this.focusId) return 1;
        return (orderIndex.get(a) || 0) - (orderIndex.get(b) || 0);
      }));

      const widest = Math.max(...Array.from(rows.values()).map((row) => row.length), 1);
      const width = Math.max(this.viewport.clientWidth || 1000, widest * (CARD.width + LAYOUT.gapX) + LAYOUT.paddingX * 2);
      const up = Number(this.ancestorRange?.value || 3);
      const down = Number(this.descendantRange?.value || 3);
      const height = Math.max(this.viewport.clientHeight || 700, (up + down + 1) * LAYOUT.rowHeight + LAYOUT.paddingY * 2);
      const centerX = width / 2;

      rows.forEach((ids, generation) => {
        const rowWidth = ids.length * CARD.width + Math.max(0, ids.length - 1) * LAYOUT.gapX;
        let startX = centerX - rowWidth / 2;
        ids.forEach((id) => {
          this.positions.set(id, {
            x: startX,
            y: LAYOUT.paddingY + (generation + up) * LAYOUT.rowHeight,
            generation,
          });
          startX += CARD.width + LAYOUT.gapX;
        });
      });

      this.setWorldSize(width, height);
      this.drawLinks();
      this.drawCards();
      this.setStatus(`${this.visibleIds.size} pessoas visíveis · foco em ${this.byId.get(this.focusId)?.name || '—'}`);
      this.updatePanel();

      requestAnimationFrame(() => {
        if (options.fit || this.firstRender) this.fitVisible();
        else if (options.center) this.centerFocus();
        this.firstRender = false;
      });
    }

    setWorldSize(width, height) {
      this.stage.style.width = `${width}px`;
      this.stage.style.height = `${height}px`;
      this.world.style.width = `${width}px`;
      this.world.style.height = `${height}px`;
      this.links.setAttribute('width', width);
      this.links.setAttribute('height', height);
      this.links.setAttribute('viewBox', `0 0 ${width} ${height}`);
    }

    drawLinks() {
      this.links.replaceChildren();
      const parentLayer = document.createElementNS(SVG_NS, 'g');
      parentLayer.classList.add('tree-link-layer', 'tree-link-parents');
      const spouseLayer = document.createElementNS(SVG_NS, 'g');
      spouseLayer.classList.add('tree-link-layer', 'tree-link-spouses');
      this.links.append(parentLayer, spouseLayer);
      const drawn = new Set();

      this.visibleIds.forEach((id) => {
        const node = this.byId.get(id);
        const from = this.positions.get(id);
        if (!node || !from) return;
        node.children.forEach((childId) => {
          const to = this.positions.get(childId);
          if (!to || !this.visibleIds.has(childId)) return;
          const key = `${id}:${childId}`;
          if (drawn.has(key)) return;
          drawn.add(key);
          const x1 = from.x + CARD.width / 2;
          const y1 = from.y + CARD.height;
          const x2 = to.x + CARD.width / 2;
          const y2 = to.y;
          const mid = y1 + (y2 - y1) / 2;
          parentLayer.append(this.path(`M ${x1} ${y1} V ${mid} H ${x2} V ${y2}`, 'tree-link tree-link-parent'));
        });
        node.spouses.forEach((spouseId) => {
          const to = this.positions.get(spouseId);
          if (!to || !this.visibleIds.has(spouseId)) return;
          const key = [id, spouseId].sort().join(':');
          if (drawn.has(`spouse:${key}`)) return;
          drawn.add(`spouse:${key}`);
          const left = from.x < to.x ? from : to;
          const right = from.x < to.x ? to : from;
          const y = left.y + CARD.height / 2;
          spouseLayer.append(this.path(`M ${left.x + CARD.width} ${y} H ${right.x}`, 'tree-link tree-link-spouse'));
        });
      });
    }

    path(d, className) {
      const path = document.createElementNS(SVG_NS, 'path');
      path.setAttribute('d', d);
      path.setAttribute('class', className);
      return path;
    }

    drawCards() {
      this.cards.replaceChildren();
      this.visibleIds.forEach((id) => {
        const node = this.byId.get(id);
        const position = this.positions.get(id);
        if (!node || !position) return;
        const card = document.createElement('article');
        card.className = `tree-card tree-card-${this.genderClass(node.gender)}${id === this.focusId ? ' is-focus' : ''}`;
        card.dataset.id = id;
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `Selecionar ${node.name}`);
        card.style.width = `${CARD.width}px`;
        card.style.height = `${CARD.height}px`;
        card.style.left = `${position.x}px`;
        card.style.top = `${position.y}px`;
        card.append(this.avatar(node));
        const content = document.createElement('div');
        content.className = 'tree-card-content';
        const name = document.createElement('h3');
        name.textContent = node.name;
        const dates = document.createElement('p');
        dates.className = 'tree-card-dates';
        dates.textContent = node.dates;
        const relation = document.createElement('span');
        relation.className = 'tree-card-relation';
        relation.textContent = id === this.focusId ? 'Pessoa em foco' : this.relationLabel(position.generation);
        content.append(name, dates, relation);
        card.append(content);
        card.addEventListener('click', () => this.select(id));
        card.addEventListener('keydown', (event) => this.cardKeydown(event, node));
        this.cards.append(card);
      });
    }

    avatar(node) {
      if (node.photo && !node.photo.startsWith('data:')) {
        const image = document.createElement('img');
        image.className = 'tree-card-avatar';
        image.src = node.photo;
        image.alt = '';
        image.loading = 'lazy';
        image.addEventListener('error', () => image.replaceWith(this.initials(node)));
        return image;
      }
      return this.initials(node);
    }

    initials(node) {
      const element = document.createElement('div');
      element.className = 'tree-card-avatar tree-card-initials';
      element.textContent = node.shortName.slice(0, 1).toUpperCase();
      return element;
    }

    genderClass(gender) {
      if (gender === 'm' || gender === 'masculino') return 'male';
      if (gender === 'f' || gender === 'feminino') return 'female';
      return 'neutral';
    }

    relationLabel(generation) {
      if (generation < 0) return `${Math.abs(generation)}ª geração acima`;
      if (generation > 0) return `${generation}ª geração abaixo`;
      return 'mesma geração';
    }

    cardKeydown(event, node) {
      const list = event.key === 'ArrowUp' ? node.parents : event.key === 'ArrowDown' ? node.children : node.spouses;
      if (event.key === 'Enter') {
        window.location.href = `pessoa.php?id=${encodeURIComponent(node.id)}`;
        return;
      }
      if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        const index = Math.max(0, list.indexOf(node.id));
        const target = list[index] || list[0];
        if (target && this.byId.has(target)) this.select(target);
      } else if ((event.key === 'ArrowUp' || event.key === 'ArrowDown') && list[0]) {
        event.preventDefault();
        this.select(list[0]);
      }
    }

    select(id) {
      id = String(id);
      if (!this.byId.has(id)) return;
      this.focusId = id;
      const url = new URL(window.location.href);
      url.searchParams.set('foco', id);
      window.history.replaceState({}, '', url);
      this.render({ center: true });
      this.search?.blur();
      if (this.results) this.results.hidden = true;
    }

    updatePanel() {
      const node = this.byId.get(this.focusId);
      if (!node || !this.panel) return;
      const set = (selector, value) => {
        const element = this.panel.querySelector(selector);
        if (element) element.textContent = value;
      };
      set('[data-person-name]', node.name);
      set('[data-person-dates]', node.dates);
      set('[data-person-location]', node.birthPlace || 'Local de nascimento não informado');
      set('[data-person-relations]', `${node.parents.length} pais · ${node.spouses.length} cônjuges · ${node.children.length} filhos`);
      const profile = this.panel.querySelector('[data-person-profile]');
      if (profile) profile.href = `pessoa.php?id=${encodeURIComponent(node.id)}`;
      const photo = this.panel.querySelector('[data-person-photo]');
      if (photo) {
        photo.src = node.photo || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"%3E%3Crect width="120" height="120" fill="%23e8e4dc"/%3E%3Ccircle cx="60" cy="43" r="23" fill="%23ada99f"/%3E%3Cpath d="M20 112c5-27 24-40 40-40s35 13 40 40" fill="%23ada99f"/%3E%3C/svg%3E';
        photo.alt = `Foto de ${node.name}`;
      }
    }

    updateSearch() {
      if (!this.results || !this.search) return;
      const query = this.search.value.trim().toLocaleLowerCase('pt-BR');
      this.results.replaceChildren();
      if (!query) {
        this.results.hidden = true;
        return;
      }
      const matches = this.nodes.filter((node) => node.name.toLocaleLowerCase('pt-BR').includes(query)).slice(0, 10);
      matches.forEach((node) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.searchId = node.id;
        button.className = 'tree-search-result';
        const title = document.createElement('strong');
        title.textContent = node.name;
        const dates = document.createElement('span');
        dates.textContent = node.dates;
        button.append(title, dates);
        button.addEventListener('click', () => this.select(node.id));
        this.results.append(button);
      });
      this.results.hidden = matches.length === 0;
    }

    zoom(factor, clientX, clientY) {
      const next = Math.max(0.38, Math.min(1.75, this.transform.scale * factor));
      if (clientX !== undefined && clientY !== undefined) {
        const rect = this.viewport.getBoundingClientRect();
        const x = clientX - rect.left;
        const y = clientY - rect.top;
        const ratio = next / this.transform.scale;
        this.transform.x = x - (x - this.transform.x) * ratio;
        this.transform.y = y - (y - this.transform.y) * ratio;
      }
      this.transform.scale = next;
      this.applyTransform();
    }

    fitVisible() {
      if (!this.graph) return;
      const width = this.stage.offsetWidth;
      const height = this.stage.offsetHeight;
      const availableWidth = Math.max(240, this.viewport.clientWidth - 48);
      const availableHeight = Math.max(240, this.viewport.clientHeight - 48);
      this.transform.scale = Math.max(0.38, Math.min(1.15, Math.min(availableWidth / width, availableHeight / height)));
      this.transform.x = (this.viewport.clientWidth - width * this.transform.scale) / 2;
      this.transform.y = (this.viewport.clientHeight - height * this.transform.scale) / 2;
      this.applyTransform();
    }

    centerFocus() {
      const position = this.positions.get(String(this.focusId));
      if (!position) return;
      this.transform.x = this.viewport.clientWidth / 2 - (position.x + CARD.width / 2) * this.transform.scale;
      this.transform.y = this.viewport.clientHeight / 2 - (position.y + CARD.height / 2) * this.transform.scale;
      this.applyTransform();
    }

    applyTransform() {
      this.world.style.transform = `translate3d(${this.transform.x}px, ${this.transform.y}px, 0) scale(${this.transform.scale})`;
      if (this.zoomLabel) this.zoomLabel.textContent = `${Math.round(this.transform.scale * 100)}%`;
    }

    togglePanel() {
      this.panelOpen = !this.panelOpen;
      document.body.classList.toggle('tree-panel-closed', !this.panelOpen);
      const button = document.querySelector('[data-tree-action="toggle-panel"]');
      if (button) {
        button.setAttribute('aria-pressed', String(this.panelOpen));
        button.textContent = this.panelOpen ? 'Detalhes' : 'Detalhes';
      }
      window.setTimeout(() => this.fitVisible(), 180);
    }

    showEmpty(title = 'A árvore ainda está vazia', message = 'Adicione a primeira pessoa para começar a conectar as gerações.') {
      if (this.empty) {
        this.empty.hidden = false;
        const heading = this.empty.querySelector('[data-empty-title]');
        const text = this.empty.querySelector('[data-empty-message]');
        if (heading) heading.textContent = title;
        if (text) text.textContent = message;
      }
      this.stage.hidden = true;
    }

    hideEmpty() {
      if (this.empty) this.empty.hidden = true;
      this.stage.hidden = false;
    }

    setStatus(text) {
      if (this.status) this.status.textContent = text;
    }
  }

  window.FamilyTreeView = FamilyTreeView;
})();
