(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const CARD_W = 208;
  const CARD_H = 104;
  const GAP_X = 34;
  const GAP_Y = 190;
  const PADDING = 90;

  class TreeExplorer {
    constructor(options) {
      this.root = document.querySelector(options.root);
      this.stage = document.querySelector(options.stage);
      this.viewport = document.querySelector(options.viewport);
      this.panel = document.querySelector(options.panel);
      this.search = document.querySelector(options.search);
      this.ancestorRange = document.querySelector(options.ancestorRange);
      this.descendantRange = document.querySelector(options.descendantRange);
      this.status = document.querySelector(options.status);
      this.badge = document.querySelector(options.badge);
      this.svg = document.createElementNS(SVG_NS, 'svg');
      this.layer = document.createElementNS(SVG_NS, 'g');
      this.edgeLayer = document.createElementNS(SVG_NS, 'g');
      this.nodeLayer = document.createElementNS(SVG_NS, 'g');
      this.svg.setAttribute('class', 'tree-svg');
      this.svg.append(this.layer);
      this.layer.append(this.edgeLayer, this.nodeLayer);
      this.stage.append(this.svg);
      this.nodes = [];
      this.byId = new Map();
      this.positions = new Map();
      this.focusId = null;
      this.transform = { x: 0, y: 0, k: 0.88 };
      this.drag = null;
      this.data = null;
      this.bind();
    }

    async load() {
      try {
        const response = await fetch('arvore_dados.php', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Falha ao carregar os dados');
        const payload = await response.json();
        this.data = payload;
        this.nodes = payload.pessoas || [];
        this.byId = new Map(this.nodes.map((node) => [String(node.id), node]));
        this.focusId = new URLSearchParams(window.location.search).get('foco') || (this.nodes[0] && String(this.nodes[0].id));
        this.badge.textContent = `${payload.totais?.pessoas || 0} pessoas`;
        if (!this.nodes.length) {
          this.stage.innerHTML = '<div class="tree-empty"><div class="tree-empty-icon">✦</div><h3>Comece pela sua primeira história</h3><p>Cadastre alguém para abrir a visualização de árvore e conectar pais, cônjuges e filhos.</p><a class="btn" href="pessoa_editar.php">Adicionar pessoa</a></div>';
          return;
        }
        this.render();
        this.updatePanel(this.byId.get(this.focusId));
      } catch (error) {
        this.stage.innerHTML = '<div class="tree-empty"><div class="tree-empty-icon">!</div><h3>Não foi possível carregar a árvore</h3><p>Verifique a conexão com o servidor e recarregue a página.</p></div>';
        console.error(error);
      }
    }

    bind() {
      document.querySelectorAll('[data-tree-action]').forEach((button) => {
        button.addEventListener('click', () => {
          const action = button.dataset.treeAction;
          if (action === 'zoom-in') this.zoom(1.18);
          if (action === 'zoom-out') this.zoom(0.84);
          if (action === 'fit') this.fit();
          if (action === 'center') this.centerFocus();
        });
      });
      this.search?.addEventListener('input', () => this.filterSearch());
      this.search?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          const first = this.searchResults?.[0];
          if (first) this.select(first.id);
        }
        if (event.key === 'Escape') {
          this.search.value = '';
          this.filterSearch();
        }
      });
      this.ancestorRange?.addEventListener('input', () => this.render());
      this.descendantRange?.addEventListener('input', () => this.render());
      this.viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        this.zoom(event.deltaY > 0 ? 0.91 : 1.1, event.clientX, event.clientY);
      }, { passive: false });
      this.viewport.addEventListener('pointerdown', (event) => {
        if (event.target.closest('.tree-node')) return;
        this.drag = { x: event.clientX, y: event.clientY, tx: this.transform.x, ty: this.transform.y };
        this.viewport.setPointerCapture(event.pointerId);
        this.viewport.classList.add('is-dragging');
      });
      this.viewport.addEventListener('pointermove', (event) => {
        if (!this.drag) return;
        this.transform.x = this.drag.tx + event.clientX - this.drag.x;
        this.transform.y = this.drag.ty + event.clientY - this.drag.y;
        this.applyTransform();
      });
      this.viewport.addEventListener('pointerup', (event) => {
        this.drag = null;
        this.viewport.releasePointerCapture?.(event.pointerId);
        this.viewport.classList.remove('is-dragging');
      });
      window.addEventListener('resize', () => this.fit());
    }

    visibleGraph() {
      const depthUp = Number(this.ancestorRange?.value || 3);
      const depthDown = Number(this.descendantRange?.value || 3);
      const focus = this.byId.get(String(this.focusId));
      if (!focus) return { ids: new Set(), generation: new Map() };
      const ids = new Set([String(focus.id)]);
      const generation = new Map([[String(focus.id), 0]]);
      const queue = [{ id: String(focus.id), gen: 0 }];
      while (queue.length) {
        const current = queue.shift();
        const node = this.byId.get(current.id);
        if (!node) continue;
        const next = [];
        if (current.gen <= 0 && Math.abs(current.gen) < depthUp) {
          next.push(...(node.rels?.parents || []).map((id) => ({ id: String(id), gen: current.gen - 1 })));
        }
        if (current.gen >= 0 && current.gen < depthDown) {
          next.push(...(node.rels?.children || []).map((id) => ({ id: String(id), gen: current.gen + 1 })));
        }
        next.push(...(node.rels?.spouses || []).map((id) => ({ id: String(id), gen: current.gen })));
        next.forEach((item) => {
          if (!this.byId.has(item.id) || ids.has(item.id)) return;
          ids.add(item.id);
          generation.set(item.id, item.gen);
          queue.push(item);
        });
      }
      return { ids, generation };
    }

    render() {
      const graph = this.visibleGraph();
      this.positions.clear();
      const rows = new Map();
      graph.ids.forEach((id) => {
        const gen = graph.generation.get(id) ?? 0;
        if (!rows.has(gen)) rows.set(gen, []);
        rows.get(gen).push(this.byId.get(id));
      });
      rows.forEach((items) => items.sort((a, b) => a.data.nome.localeCompare(b.data.nome, 'pt-BR')));
      const maxWidth = Math.max(...Array.from(rows.values()).map((items) => items.length), 1) * (CARD_W + GAP_X);
      const centerX = maxWidth / 2;
      rows.forEach((items, gen) => {
        const rowWidth = items.length * CARD_W + (items.length - 1) * GAP_X;
        const startX = centerX - rowWidth / 2;
        items.forEach((node, index) => {
          const x = startX + index * (CARD_W + GAP_X);
          const y = PADDING + (gen + Number(this.ancestorRange?.value || 3)) * GAP_Y;
          this.positions.set(String(node.id), { x, y, gen });
        });
      });
      const width = Math.max(maxWidth + PADDING * 2, this.viewport.clientWidth || 900);
      const height = (Number(this.ancestorRange?.value || 3) + Number(this.descendantRange?.value || 3) + 1) * GAP_Y + PADDING * 2;
      this.svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
      this.svg.setAttribute('width', width);
      this.svg.setAttribute('height', height);
      this.edgeLayer.replaceChildren();
      this.nodeLayer.replaceChildren();
      this.drawEdges(graph.ids);
      graph.ids.forEach((id) => this.drawNode(this.byId.get(id)));
      this.applyTransform();
      this.status.textContent = `${graph.ids.size} pessoas visíveis · foco em ${this.byId.get(String(this.focusId))?.data.nome || '—'}`;
      this.updatePanel(this.byId.get(String(this.focusId)));
      if (!this._hasFit) requestAnimationFrame(() => this.fit());
    }

    drawEdges(ids) {
      const drawn = new Set();
      ids.forEach((id) => {
        const node = this.byId.get(id);
        const from = this.positions.get(id);
        if (!node || !from) return;
        (node.rels?.children || []).forEach((childId) => {
          const child = String(childId);
          const to = this.positions.get(child);
          if (!to || !ids.has(child)) return;
          const key = `p-${id}-${child}`;
          if (drawn.has(key)) return;
          drawn.add(key);
          const line = document.createElementNS(SVG_NS, 'path');
          const x1 = from.x + CARD_W / 2;
          const y1 = from.y + CARD_H;
          const x2 = to.x + CARD_W / 2;
          const y2 = to.y;
          const midY = y1 + (y2 - y1) / 2;
          line.setAttribute('d', `M ${x1} ${y1} V ${midY} H ${x2} V ${y2}`);
          line.setAttribute('class', 'tree-edge tree-edge-parent');
          this.edgeLayer.append(line);
        });
        (node.rels?.spouses || []).forEach((spouseId) => {
          const spouse = String(spouseId);
          const to = this.positions.get(spouse);
          if (!to || !ids.has(spouse)) return;
          const key = [id, spouse].sort().join('-');
          if (drawn.has(`s-${key}`)) return;
          drawn.add(`s-${key}`);
          const line = document.createElementNS(SVG_NS, 'path');
          line.setAttribute('d', `M ${from.x + CARD_W} ${from.y + CARD_H / 2} H ${to.x}`);
          line.setAttribute('class', 'tree-edge tree-edge-spouse');
          this.edgeLayer.append(line);
        });
      });
    }

    drawNode(node) {
      const pos = this.positions.get(String(node.id));
      if (!pos) return;
      const group = document.createElementNS(SVG_NS, 'g');
      group.setAttribute('class', `tree-node ${String(node.id) === String(this.focusId) ? 'is-focus' : ''}`);
      group.setAttribute('transform', `translate(${pos.x},${pos.y})`);
      group.dataset.id = node.id;
      const rect = document.createElementNS(SVG_NS, 'rect');
      rect.setAttribute('class', `tree-card tree-card-${(node.data.gender || 'neutral').toLowerCase()}`);
      rect.setAttribute('rx', 18);
      rect.setAttribute('width', CARD_W);
      rect.setAttribute('height', CARD_H);
      group.append(rect);
      const avatar = document.createElementNS(SVG_NS, 'circle');
      avatar.setAttribute('cx', 35);
      avatar.setAttribute('cy', 38);
      avatar.setAttribute('r', 23);
      avatar.setAttribute('class', 'tree-avatar');
      group.append(avatar);
      const initials = document.createElementNS(SVG_NS, 'text');
      initials.setAttribute('x', 35);
      initials.setAttribute('y', 43);
      initials.setAttribute('text-anchor', 'middle');
      initials.setAttribute('class', 'tree-avatar-text');
      initials.textContent = (node.data.nomeCurto || node.data.nome || '?').slice(0, 1).toUpperCase();
      group.append(initials);
      const name = document.createElementNS(SVG_NS, 'text');
      name.setAttribute('x', 70);
      name.setAttribute('y', 32);
      name.setAttribute('class', 'tree-name');
      name.textContent = this.truncate(node.data.nome, 22);
      group.append(name);
      const dates = document.createElementNS(SVG_NS, 'text');
      dates.setAttribute('x', 70);
      dates.setAttribute('y', 55);
      dates.setAttribute('class', 'tree-dates');
      dates.textContent = node.data.datas || 'sem datas';
      group.append(dates);
      const relation = document.createElementNS(SVG_NS, 'text');
      relation.setAttribute('x', 70);
      relation.setAttribute('y', 78);
      relation.setAttribute('class', 'tree-relation');
      relation.textContent = String(node.id) === String(this.focusId) ? 'pessoa em foco' : this.relationLabel(pos.gen);
      group.append(relation);
      group.addEventListener('click', (event) => {
        event.stopPropagation();
        this.select(node.id);
      });
      group.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') this.select(node.id);
      });
      group.setAttribute('tabindex', '0');
      this.nodeLayer.append(group);
    }

    relationLabel(gen) {
      if (gen < 0) return `ascendente · ${Math.abs(gen)}ª geração`;
      if (gen > 0) return `descendente · ${gen}ª geração`;
      return 'mesma geração';
    }

    truncate(value, size) {
      return value.length > size ? `${value.slice(0, size - 1)}…` : value;
    }

    select(id) {
      if (!this.byId.has(String(id))) return;
      this.focusId = String(id);
      const url = new URL(window.location.href);
      url.searchParams.set('foco', this.focusId);
      window.history.replaceState({}, '', url);
      this._hasFit = false;
      this.render();
      this.updatePanel(this.byId.get(this.focusId));
    }

    updatePanel(node) {
      if (!this.panel || !node) return;
      const name = this.panel.querySelector('[data-person-name]');
      const dates = this.panel.querySelector('[data-person-dates]');
      const location = this.panel.querySelector('[data-person-location]');
      const relations = this.panel.querySelector('[data-person-relations]');
      const link = this.panel.querySelector('[data-person-profile]');
      if (name) name.textContent = node.data.nome;
      if (dates) dates.textContent = node.data.datas || 'Datas não informadas';
      if (location) location.textContent = node.data.localNascimento || 'Local de nascimento não informado';
      if (relations) relations.textContent = `${node.rels?.parents?.length || 0} pais · ${node.rels?.spouses?.length || 0} cônjuges · ${node.rels?.children?.length || 0} filhos`;
      if (link) link.href = `pessoa.php?id=${encodeURIComponent(node.id)}`;
    }

    filterSearch() {
      const query = this.search.value.trim().toLocaleLowerCase('pt-BR');
      const results = this.nodes.filter((node) => node.data.nome.toLocaleLowerCase('pt-BR').includes(query)).slice(0, 8);
      this.searchResults = results;
      const list = document.querySelector('[data-search-results]');
      if (!list) return;
      list.replaceChildren();
      if (!query) {
        list.hidden = true;
        return;
      }
      results.forEach((node) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'search-result';
        item.innerHTML = `<strong>${this.escape(node.data.nome)}</strong><span>${this.escape(node.data.datas || 'Sem datas')}</span>`;
        item.addEventListener('click', () => {
          this.search.value = node.data.nome;
          list.hidden = true;
          this.select(node.id);
        });
        list.append(item);
      });
      list.hidden = results.length === 0;
    }

    escape(value) {
      const div = document.createElement('div');
      div.textContent = value;
      return div.innerHTML;
    }

    zoom(factor, clientX, clientY) {
      const old = this.transform.k;
      const next = Math.max(0.45, Math.min(1.65, old * factor));
      if (clientX !== undefined) {
        const rect = this.viewport.getBoundingClientRect();
        const px = clientX - rect.left;
        const py = clientY - rect.top;
        this.transform.x = px - (px - this.transform.x) * (next / old);
        this.transform.y = py - (py - this.transform.y) * (next / old);
      }
      this.transform.k = next;
      this.applyTransform();
    }

    applyTransform() {
      this.layer.setAttribute('transform', `translate(${this.transform.x},${this.transform.y}) scale(${this.transform.k})`);
    }

    centerFocus() {
      const pos = this.positions.get(String(this.focusId));
      if (!pos) return;
      this.transform.x = this.viewport.clientWidth / 2 - (pos.x + CARD_W / 2) * this.transform.k;
      this.transform.y = this.viewport.clientHeight / 2 - (pos.y + CARD_H / 2) * this.transform.k;
      this.applyTransform();
    }

    fit() {
      if (!this.svg?.getAttribute('width')) return;
      const width = Number(this.svg.getAttribute('width'));
      const height = Number(this.svg.getAttribute('height'));
      const scale = Math.min((this.viewport.clientWidth - 56) / width, (this.viewport.clientHeight - 56) / height, 1.05);
      this.transform.k = Math.max(0.45, Math.min(1.05, scale));
      this.transform.x = (this.viewport.clientWidth - width * this.transform.k) / 2;
      this.transform.y = (this.viewport.clientHeight - height * this.transform.k) / 2;
      this.applyTransform();
      this._hasFit = true;
    }
  }

  window.TreeExplorer = TreeExplorer;
})();
