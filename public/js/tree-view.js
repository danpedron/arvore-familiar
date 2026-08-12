(() => {
  'use strict';

  const SVG_NS = 'http://www.w3.org/2000/svg';
  const CARD = { width: 204, height: 86, gapX: 28, gapY: 116 };
  const END_STATUSES = new Set(['divorciado', 'encerrado', 'viuvo']);

  class FamilyTreeView {
    constructor(options) {
      this.viewport = document.querySelector(options.viewport);
      this.stage = document.querySelector(options.stage);
      this.panel = document.querySelector(options.panel);
      this.search = document.querySelector(options.search);
      this.results = document.querySelector(options.results);
      this.ancestorRange = document.querySelector(options.ancestorRange);
      this.descendantRange = document.querySelector(options.descendantRange);
      this.status = document.querySelector(options.status);
      this.zoomLabel = document.querySelector(options.zoomLabel);
      this.sort = document.querySelector(options.sort);
      this.mode = document.querySelector(options.mode);
      this.total = document.querySelector(options.total);
      this.empty = document.querySelector(options.empty);
      this.breadcrumb = document.querySelector(options.breadcrumb);
      this.csrf = options.csrf || '';
      this.nodes = [];
      this.byId = new Map();
      this.visibleIds = new Set();
      this.positions = new Map();
      this.unionMarkers = [];
      this.focusId = new URL(window.location.href).searchParams.get('foco');
      this.transform = { x: 0, y: 0, scale: 0.9 };
      this.drag = null;
      this.panelOpen = true;
      this.data = null;
      this.graph = null;
      this.modeValue = this.mode?.value || 'explorer';
      this.sortValue = this.sort?.value || 'nome_asc';
      this.bind();
    }

    bind() {
      if (!this.viewport) return;
      this.viewport.addEventListener('wheel', (event) => {
        event.preventDefault();
        this.zoom(event.deltaY > 0 ? 0.9 : 1.1, event.clientX, event.clientY);
      }, { passive: false });
      this.viewport.addEventListener('pointerdown', (event) => this.startPan(event));
      window.addEventListener('pointermove', (event) => this.movePan(event));
      window.addEventListener('pointerup', () => this.endPan());
      this.viewport.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') this.search?.blur();
      });
      this.search?.addEventListener('input', () => this.updateSearch());
      this.search?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          const first = this.results?.querySelector('[data-search-id]');
          if (first) this.select(first.dataset.searchId, { center: true });
        }
      });
      this.ancestorRange?.addEventListener('input', () => this.render({ center: false }));
      this.descendantRange?.addEventListener('input', () => this.render({ center: false }));
      this.sort?.addEventListener('change', () => { this.sortValue = this.sort.value; this.render({ center: false }); });
      this.mode?.addEventListener('change', () => { this.modeValue = this.mode.value; this.render({ center: true }); });
      document.querySelectorAll('[data-tree-action]').forEach((button) => {
        button.addEventListener('click', () => this.action(button.dataset.treeAction));
      });
      document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
      });
      document.querySelector('[data-follow-form]')?.addEventListener('submit', (event) => this.saveFollowedTree(event));
    }

    async load() {
      this.setStatus('Carregando a árvore…');
      try {
        const response = await fetch('arvore_dados.php', { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        this.data = await response.json();
        this.nodes = (this.data.pessoas || []).map((person) => ({
          id: String(person.id), name: person.nome || 'Sem nome', shortName: person.nomeCurto || person.nome || '?',
          gender: String(person.gender || 'neutral').toLowerCase(), sex: person.sexo || 'Desconhecido',
          dates: person.datas || 'Datas não informadas', birth: person.nascimento || null, death: person.dataFalecimento || null,
          birthPlace: person.localNascimento || '', photo: person.foto || '', status: person.status || 'vivo',
          parents: (person.pais || []).map(String), children: (person.filhos || []).map(String), spouses: (person.conjuges || []).map(String),
          unions: Array.isArray(person.unioes) ? person.unioes : [], updated: person.atualizadoEm || '', created: person.criadoEm || '',
        }));
        this.byId = new Map(this.nodes.map((node) => [node.id, node]));
        if (!this.nodes.length) return this.showEmpty();
        if (!this.focusId || !this.byId.has(this.focusId)) this.focusId = this.nodes[0].id;
        this.hideEmpty();
        this.render({ center: true });
        const total = this.data.totais?.pessoas || this.nodes.length;
        if (this.total) this.total.textContent = `${this.nodes.length} de ${total} pessoas`;
      } catch (error) {
        this.showEmpty('Não foi possível carregar a árvore', 'Atualize a página e tente novamente.');
        this.setStatus(`Erro ao carregar a árvore: ${error.message}`);
      }
    }

    action(name) {
      if (name === 'zoom-in') this.zoom(1.15);
      if (name === 'zoom-out') this.zoom(0.87);
      if (name === 'fit') this.fitVisible();
      if (name === 'center') this.centerFocus();
      if (name === 'toggle-panel') this.togglePanel();
      if (name === 'edit') this.openEditPanel();
      if (name === 'add') this.openAddMenu();
      if (name === 'more') this.openMoreMenu();
      if (name === 'media') this.openProfileMedia();
      if (name === 'export') this.exportPdf();
      if (name === 'follow') document.querySelector('#follow-dialog')?.showModal();
      if (name === 'import') this.openImporter();
    }

    render(options = {}) {
      if (!this.byId.size || !this.focusId) return;
      this.graph = this.buildGraph();
      this.positions = this.layoutGraph(this.graph);
      this.stage.replaceChildren();
      this.stage.className = `tree-stage tree-mode-${this.modeValue}`;
      const bounds = this.bounds();
      const world = document.createElement('div');
      world.className = 'tree-world';
      world.style.width = `${bounds.width}px`;
      world.style.height = `${bounds.height}px`;
      const links = document.createElementNS(SVG_NS, 'svg');
      links.classList.add('tree-links');
      links.setAttribute('width', String(bounds.width));
      links.setAttribute('height', String(bounds.height));
      links.setAttribute('viewBox', `0 0 ${bounds.width} ${bounds.height}`);
      const cards = document.createElement('div');
      cards.className = 'tree-cards';
      world.append(links, cards);
      this.stage.append(world);
      this.world = world;
      this.links = links;
      this.cards = cards;
      this.drawLinks();
      this.drawCards();
      this.drawUnionMarkers();
      this.drawAddSlots();
      this.updatePanel();
      this.applyTransform();
      if (options.center) this.centerFocus();
      else if (!options.preserve) this.fitVisible();
      this.setStatus(`${this.visibleIds.size} pessoas visíveis · modo ${this.modeLabel()}`);
    }

    buildGraph() {
      const ancestors = Math.max(1, Number(this.ancestorRange?.value || 2));
      const descendants = Math.max(1, Number(this.descendantRange?.value || 2));
      const generation = new Map([[String(this.focusId), 0]]);
      const queue = [[String(this.focusId), 0]];
      const include = new Set([String(this.focusId)]);
      while (queue.length) {
        const [id, level] = queue.shift();
        const node = this.byId.get(id);
        if (!node) continue;
        const nextParents = level > -ancestors ? node.parents : [];
        const nextChildren = level < descendants ? node.children : [];
        nextParents.forEach((pid) => this.addGraphNode(pid, level - 1, include, generation, queue));
        nextChildren.forEach((cid) => this.addGraphNode(cid, level + 1, include, generation, queue));
        node.spouses.forEach((sid) => this.addGraphNode(sid, level, include, generation, queue));
      }
      include.forEach((id) => {
        const node = this.byId.get(id);
        if (!node) return;
        node.parents.forEach((pid) => { if (generation.has(id) && generation.get(id) < 0 && !generation.has(pid)) generation.set(pid, generation.get(id) - 1); });
      });
      this.visibleIds = include;
      return { generation, ancestors, descendants };
    }

    addGraphNode(id, level, include, generation, queue) {
      id = String(id);
      if (!this.byId.has(id)) return;
      if (!include.has(id)) { include.add(id); generation.set(id, level); queue.push([id, level]); }
      else if (!generation.has(id)) generation.set(id, level);
    }

    sorted(ids) {
      const values = ids.map(String);
      const dateValue = (node) => node.birth ? Date.parse(node.birth) || Number.MAX_SAFE_INTEGER : Number.MAX_SAFE_INTEGER;
      values.sort((a, b) => {
        const left = this.byId.get(a); const right = this.byId.get(b);
        if (!left || !right) return 0;
        if (this.sortValue === 'nome_desc') return right.name.localeCompare(left.name, 'pt-BR');
        if (this.sortValue === 'nascimento_asc') return dateValue(left) - dateValue(right) || left.name.localeCompare(right.name, 'pt-BR');
        if (this.sortValue === 'nascimento_desc') return dateValue(right) - dateValue(left) || left.name.localeCompare(right.name, 'pt-BR');
        if (this.sortValue === 'atualizado_desc') return String(right.updated).localeCompare(String(left.updated)) || left.name.localeCompare(right.name, 'pt-BR');
        return left.name.localeCompare(right.name, 'pt-BR');
      });
      return values;
    }

    layoutGraph(graph) {
      const positions = new Map();
      if (this.modeValue === 'fan') return this.layoutFan(graph, positions);
      const rows = new Map();
      this.visibleIds.forEach((id) => {
        const level = graph.generation.get(id) ?? 0;
        if (!rows.has(level)) rows.set(level, []);
        rows.get(level).push(id);
      });
      const levels = [...rows.keys()].sort((a, b) => a - b);
      if (this.modeValue === 'lineage') {
        levels.forEach((level, column) => {
          const ids = this.sorted(rows.get(level));
          ids.forEach((id, row) => positions.set(id, { x: column * 270 + 50, y: row * (CARD.height + 24) + 50, generation: level }));
        });
      } else {
        levels.forEach((level, rowIndex) => {
          const ids = this.sorted(rows.get(level));
          const grouped = this.groupCouples(ids);
          let x = 40;
          grouped.forEach((group) => {
            group.forEach((id) => { positions.set(id, { x, y: rowIndex * CARD.gapY + 50, generation: level }); x += CARD.width + CARD.gapX; });
            x += 22;
          });
        });
      }
      this.normalizePositions(positions);
      return positions;
    }

    groupCouples(ids) {
      const left = new Set(ids);
      const groups = [];
      this.sorted(ids).forEach((id) => {
        if (!left.has(id)) return;
        left.delete(id);
        const node = this.byId.get(id);
        const spouse = this.sorted(node.spouses.filter((sid) => left.has(sid) && this.visibleIds.has(sid)))[0];
        if (spouse) { left.delete(spouse); groups.push([id, spouse]); }
        else groups.push([id]);
      });
      return groups;
    }

    layoutFan(graph, positions) {
      const centerX = 560; const centerY = 420;
      positions.set(String(this.focusId), { x: centerX - CARD.width / 2, y: centerY - CARD.height / 2, generation: 0 });
      [-1, 1].forEach((direction) => {
        const ids = this.sorted([...this.visibleIds].filter((id) => (graph.generation.get(id) || 0) * direction > 0));
        const groups = [...new Set(ids.map((id) => graph.generation.get(id)))].sort((a, b) => Math.abs(a) - Math.abs(b));
        groups.forEach((level) => {
          const members = ids.filter((id) => graph.generation.get(id) === level);
          const radius = 190 * Math.abs(level);
          const start = direction < 0 ? 220 : 40;
          const end = direction < 0 ? 320 : 140;
          members.forEach((id, index) => {
            const ratio = members.length === 1 ? 0.5 : index / (members.length - 1);
            const angle = (start + (end - start) * ratio) * Math.PI / 180;
            positions.set(id, { x: centerX + Math.cos(angle) * radius - CARD.width / 2, y: centerY + Math.sin(angle) * radius - CARD.height / 2, generation: level });
          });
        });
      });
      this.visibleIds.forEach((id) => {
        if (positions.has(id)) return;
        const node = this.byId.get(id);
        const anchor = positions.get(node?.spouses.find((sid) => positions.has(sid)) || this.focusId) || positions.get(this.focusId);
        positions.set(id, { x: anchor.x + CARD.width + 34, y: anchor.y, generation: graph.generation.get(id) || 0 });
      });
      this.normalizePositions(positions);
      return positions;
    }

    normalizePositions(positions) {
      const values = [...positions.values()];
      if (!values.length) return;
      const minX = Math.min(...values.map((p) => p.x)); const minY = Math.min(...values.map((p) => p.y));
      if (minX < 30 || minY < 30) positions.forEach((p) => { p.x += 30 - Math.min(0, minX); p.y += 30 - Math.min(0, minY); });
    }

    bounds() {
      const values = [...this.positions.values()];
      const maxX = values.length ? Math.max(...values.map((p) => p.x + CARD.width)) : 600;
      const maxY = values.length ? Math.max(...values.map((p) => p.y + CARD.height)) : 400;
      return { width: Math.max(640, maxX + 180), height: Math.max(460, maxY + 180) };
    }

    drawLinks() {
      const parentLayer = document.createElementNS(SVG_NS, 'g'); parentLayer.classList.add('tree-link-layer');
      const unionLayer = document.createElementNS(SVG_NS, 'g'); unionLayer.classList.add('tree-union-layer');
      this.links.append(parentLayer, unionLayer);
      const drawn = new Set();
      this.visibleIds.forEach((id) => {
        const node = this.byId.get(id); const from = this.positions.get(id); if (!node || !from) return;
        node.children.forEach((childId) => {
          const to = this.positions.get(childId); if (!to) return;
          const key = `${id}:${childId}`; if (drawn.has(key)) return; drawn.add(key);
          const x1 = from.x + CARD.width / 2; const y1 = from.y + CARD.height; const x2 = to.x + CARD.width / 2; const y2 = to.y;
          const middle = y1 + (y2 - y1) / 2;
          parentLayer.append(this.path(`M ${x1} ${y1} V ${middle} H ${x2} V ${y2}`, 'tree-link tree-link-parent'));
        });
        node.spouses.forEach((spouseId) => {
          const to = this.positions.get(spouseId); if (!to) return;
          const key = [id, spouseId].sort().join(':'); if (drawn.has(`union:${key}`)) return; drawn.add(`union:${key}`);
          const left = from.x < to.x ? from : to; const right = from.x < to.x ? to : from;
          const union = this.unionBetween(id, spouseId); const former = this.isFormerUnion(union);
          const line = this.path(`M ${left.x + CARD.width} ${left.y + CARD.height / 2} H ${right.x}`, `tree-link tree-link-union${former ? ' is-former' : ''}`);
          unionLayer.append(line);
        });
      });
    }

    path(d, className) { const path = document.createElementNS(SVG_NS, 'path'); path.setAttribute('d', d); path.setAttribute('class', className); return path; }

    drawCards() {
      this.cards.replaceChildren();
      this.visibleIds.forEach((id) => {
        const node = this.byId.get(id); const position = this.positions.get(id); if (!node || !position) return;
        const former = id !== this.focusId && this.isFormerUnion(this.unionBetween(id, this.focusId));
        const card = document.createElement('article');
        card.className = `tree-card tree-card-${this.genderClass(node.gender)}${id === this.focusId ? ' is-focus' : ''}${former ? ' is-former' : ''}${node.status === 'falecido' ? ' is-deceased' : ''}`;
        card.dataset.id = id; card.tabIndex = 0; card.setAttribute('role', 'button');
        card.style.width = `${CARD.width}px`; card.style.height = `${CARD.height}px`; card.style.left = `${position.x}px`; card.style.top = `${position.y}px`;
        card.append(this.avatar(node));
        const content = document.createElement('div'); content.className = 'tree-card-content';
        const name = document.createElement('h3'); name.textContent = node.name;
        const dates = document.createElement('p'); dates.className = 'tree-card-dates'; dates.textContent = node.dates;
        const note = document.createElement('span'); note.className = 'tree-card-relation'; note.textContent = former ? 'Ex-união' : (id === this.focusId ? 'Em foco' : this.relationLabel(position.generation));
        content.append(name, dates, note); card.append(content);
        if (former) { const badge = document.createElement('span'); badge.className = 'tree-former-badge'; badge.textContent = 'ex'; card.append(badge); }
        const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'tree-card-edit'; edit.title = `Editar ${node.name}`; edit.textContent = '✎'; edit.addEventListener('click', (event) => { event.stopPropagation(); this.select(id); this.openEditPanel(); }); card.append(edit);
        card.addEventListener('click', () => this.select(id)); card.addEventListener('keydown', (event) => this.cardKeydown(event, node));
        this.cards.append(card);
      });
    }

    drawUnionMarkers() {
      this.unionMarkers = [];
      const seen = new Set();
      this.visibleIds.forEach((id) => {
        const node = this.byId.get(id); const from = this.positions.get(id); if (!node || !from) return;
        node.spouses.forEach((spouseId) => {
          const to = this.positions.get(spouseId); if (!to) return;
          const key = [id, spouseId].sort().join(':'); if (seen.has(key)) return; seen.add(key);
          const left = from.x < to.x ? from : to; const right = from.x < to.x ? to : from; const union = this.unionBetween(id, spouseId);
          const marker = document.createElement('span'); marker.className = `tree-union-marker${this.isFormerUnion(union) ? ' is-former' : ''}`; marker.textContent = this.isFormerUnion(union) ? 'ex' : '◆'; marker.title = union?.status ? `União: ${union.status}` : 'União';
          marker.style.left = `${(left.x + CARD.width + right.x) / 2 - 11}px`; marker.style.top = `${left.y + CARD.height / 2 - 11}px`; this.cards.append(marker);
        });
      });
    }

    drawAddSlots() {
      if (!this.focusId || !this.byId.has(this.focusId)) return;
      const node = this.byId.get(this.focusId); if (!node) return;
      const parentSlots = Math.max(0, 2 - node.parents.length);
      const childSlots = Math.max(0, 1 - node.children.length);
      if (!this.canEdit()) return;
      for (let index = 0; index < parentSlots; index += 1) this.addSlot('Adicionar pai/mãe', 'pai_mae', this.positions.get(this.focusId), -1, index);
      for (let index = 0; index < childSlots; index += 1) this.addSlot('Adicionar filho', 'filho', this.positions.get(this.focusId), 1, index);
    }

    addSlot(label, type, focus, direction, index) {
      if (!focus) return;
      const slot = document.createElement('a'); slot.className = 'tree-add-slot'; slot.textContent = `＋ ${label}`; slot.href = `pessoa_editar.php?vincular_a=${encodeURIComponent(this.focusId)}&tipo_vinculo=${encodeURIComponent(type)}`;
      slot.style.left = `${focus.x + (index === 0 ? -CARD.width - 18 : CARD.width + 18)}px`; slot.style.top = `${focus.y + direction * CARD.gapY}px`; this.cards.append(slot);
    }

    avatar(node) {
      if (node.photo && !node.photo.startsWith('data:')) { const image = document.createElement('img'); image.className = 'tree-card-avatar'; image.src = node.photo; image.alt = ''; image.loading = 'lazy'; image.addEventListener('error', () => image.replaceWith(this.initials(node))); return image; }
      return this.initials(node);
    }
    initials(node) { const element = document.createElement('div'); element.className = 'tree-card-avatar tree-card-initials'; element.textContent = node.shortName.slice(0, 1).toUpperCase(); return element; }
    genderClass(gender) { if (gender === 'm' || gender === 'masculino') return 'male'; if (gender === 'f' || gender === 'feminino') return 'female'; return 'neutral'; }
    relationLabel(generation) { if (generation < 0) return `${Math.abs(generation)}ª geração acima`; if (generation > 0) return `${generation}ª geração abaixo`; return 'mesma geração'; }
    modeLabel() { return this.modeValue === 'fan' ? 'leque' : this.modeValue === 'lineage' ? 'linhagem' : 'explorador'; }
    canEdit() { return document.body.dataset.canEdit !== 'false' && !!document.querySelector('[data-tree-action="edit"]'); }

    unionBetween(a, b) {
      const node = this.byId.get(String(a)); if (!node) return null;
      return node.unions.find((union) => String(union.pessoa1) === String(b) || String(union.pessoa2) === String(b)) || null;
    }
    isFormerUnion(union) { return !!union && (END_STATUSES.has(String(union.status).toLowerCase()) || !!union.fim); }

    cardKeydown(event, node) {
      if (event.key === 'Enter') { window.location.href = `pessoa.php?id=${encodeURIComponent(node.id)}`; return; }
      const target = this.keyboardTarget(node, event.key); if (!target) return; event.preventDefault(); this.select(target, { center: event.key === 'ArrowUp' || event.key === 'ArrowDown' });
    }
    keyboardTarget(node, key) {
      const position = this.positions.get(node.id); if (!position) return null;
      if (key === 'ArrowUp' || key === 'ArrowDown') return this.closestByX((key === 'ArrowUp' ? node.parents : node.children).filter((id) => this.visibleIds.has(id)), position.x);
      if (key !== 'ArrowLeft' && key !== 'ArrowRight') return null;
      const neighbors = [...new Set([...node.spouses, ...this.visibleIds])].filter((id) => id !== node.id && this.visibleIds.has(id) && this.positions.has(id) && this.graph.generation.get(id) === this.graph.generation.get(node.id)).sort((a, b) => this.positions.get(a).x - this.positions.get(b).x);
      const directional = neighbors.filter((id) => key === 'ArrowLeft' ? this.positions.get(id).x < position.x : this.positions.get(id).x > position.x);
      return directional[0] || (key === 'ArrowLeft' ? neighbors[neighbors.length - 1] : neighbors[0]) || null;
    }
    closestByX(ids, x) { return ids.sort((a, b) => Math.abs((this.positions.get(a)?.x || 0) - x) - Math.abs((this.positions.get(b)?.x || 0) - x))[0] || null; }

    select(id, options = {}) {
      id = String(id); if (!this.byId.has(id)) return; this.focusId = id;
      const url = new URL(window.location.href); url.searchParams.set('foco', id); window.history.replaceState({}, '', url);
      this.render(options.center ? { center: true } : { preserve: true }); this.search?.blur(); if (this.results) this.results.hidden = true;
    }

    updatePanel() {
      const node = this.byId.get(String(this.focusId)); if (!node || !this.panel) return;
      const set = (selector, value) => { const element = this.panel.querySelector(selector); if (element) element.textContent = value; };
      set('[data-person-name]', node.name); set('[data-person-dates]', node.dates); set('[data-person-location]', node.birthPlace || 'Local de nascimento não informado');
      set('[data-person-relations]', `${node.parents.length} pais · ${node.spouses.length} cônjuges · ${node.children.length} filhos`);
      if (this.breadcrumb) this.breadcrumb.textContent = node.name;
      const profile = this.panel.querySelector('[data-person-profile]'); if (profile) profile.href = `pessoa.php?id=${encodeURIComponent(node.id)}`;
      const photo = this.panel.querySelector('[data-person-photo]'); if (photo) { photo.src = node.photo || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120"%3E%3Crect width="120" height="120" fill="%23ecebe7"/%3E%3Ccircle cx="60" cy="43" r="23" fill="%23aaa9a3"/%3E%3Cpath d="M20 112c5-27 24-40 40-40s35 13 40 40" fill="%23aaa9a3"/%3E%3C/svg%3E'; photo.alt = `Foto de ${node.name}`; }
      const list = this.panel.querySelector('[data-person-relation-list]');
      if (list) { list.replaceChildren(); [...node.parents, ...node.spouses, ...node.children].slice(0, 8).forEach((id) => { const person = this.byId.get(id); if (!person) return; const button = document.createElement('button'); button.type = 'button'; button.className = 'relation-list-item'; button.textContent = person.name; button.addEventListener('click', () => this.select(id, { center: true })); list.append(button); }); }
    }

    updateSearch() {
      if (!this.results || !this.search) return; const query = this.search.value.trim().toLocaleLowerCase('pt-BR'); this.results.replaceChildren(); if (!query) { this.results.hidden = true; return; }
      const matches = this.nodes.filter((node) => node.name.toLocaleLowerCase('pt-BR').includes(query)).slice(0, 10);
      matches.forEach((node) => { const button = document.createElement('button'); button.type = 'button'; button.dataset.searchId = node.id; button.className = 'tree-search-result'; const title = document.createElement('strong'); title.textContent = node.name; const dates = document.createElement('span'); dates.textContent = node.dates; button.append(title, dates); button.addEventListener('click', () => this.select(node.id, { center: true })); this.results.append(button); });
      this.results.hidden = matches.length === 0;
    }

    openEditPanel() {
      const node = this.byId.get(String(this.focusId)); if (!node || !this.canEdit()) return;
      const old = this.panel.querySelector('.sidebar-edit-form'); if (old) return;
      const form = document.createElement('form'); form.className = 'sidebar-edit-form'; form.innerHTML = `<div class="edit-form-title">Editar detalhes</div><label>Nome de nascimento<input name="nome_completo" required value="${this.escape(node.name)}"></label><label>Apelido<input name="apelido" value="${this.escape(node.shortName === node.name ? '' : node.shortName)}"></label><label>Sexo<select name="sexo"><option value="Desconhecido">Não informado</option><option value="M">Masculino</option><option value="F">Feminino</option><option value="Outro">Outro</option></select></label><label>Nascimento<input name="data_nascimento" type="date" value="${this.escape(node.birth || '')}"></label><label>Local de nascimento<input name="local_nascimento" value="${this.escape(node.birthPlace || '')}"></label><label>Falecimento<input name="data_falecimento" type="date" value="${this.escape(node.death || '')}"></label><div class="edit-form-actions"><button type="button" class="btn btn-secundario" data-cancel-edit>Cancelar</button><button class="btn" type="submit">Salvar</button></div><p class="form-feedback" data-edit-feedback></p>`;
      form.querySelector('[name="sexo"]').value = node.sex; form.addEventListener('submit', (event) => this.saveEdit(event, form)); form.querySelector('[data-cancel-edit]').addEventListener('click', () => form.remove()); this.panel.append(form);
    }

    async saveEdit(event, form) {
      event.preventDefault(); const feedback = form.querySelector('[data-edit-feedback]'); const data = Object.fromEntries(new FormData(form).entries());
      try { const response = await fetch('arvore_editar.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ csrf_token: this.csrf, id: this.focusId, data }) }); const result = await response.json(); if (!response.ok || !result.sucesso) throw new Error(result.erro || 'Não foi possível salvar.'); feedback.textContent = 'Salvo.'; form.remove(); await this.load(); this.focusId = String(result.id || this.focusId); this.render({ center: false }); } catch (error) { feedback.textContent = error.message; feedback.className = 'form-feedback is-error'; }
    }

    openAddMenu() {
      const node = this.byId.get(String(this.focusId)); if (!node) return; const old = this.panel.querySelector('.sidebar-add-menu'); if (old) { old.remove(); return; }
      const menu = document.createElement('div'); menu.className = 'sidebar-add-menu'; menu.innerHTML = `<strong>Adicionar relação</strong><a href="pessoa_editar.php?vincular_a=${encodeURIComponent(node.id)}&tipo_vinculo=pai_mae">＋ Pai ou mãe</a><a href="pessoa_editar.php?vincular_a=${encodeURIComponent(node.id)}&tipo_vinculo=filho">＋ Filho(a)</a><a href="pessoa_editar.php?vincular_a=${encodeURIComponent(node.id)}&tipo_vinculo=conjuge">＋ Cônjuge</a>`; this.panel.append(menu);
    }
    openMoreMenu() { const node = this.byId.get(String(this.focusId)); if (!node) return; const old = this.panel.querySelector('.sidebar-more-menu'); if (old) return old.remove(); const menu = document.createElement('div'); menu.className = 'sidebar-more-menu'; menu.innerHTML = `<a href="pessoa.php?id=${encodeURIComponent(node.id)}">Abrir página completa</a><button type="button" data-tree-action="center">Centralizar no canvas</button>`; menu.querySelector('button').addEventListener('click', () => { menu.remove(); this.centerFocus(); }); this.panel.append(menu); }
    openProfileMedia() { if (this.focusId) window.location.href = `pessoa.php?id=${encodeURIComponent(this.focusId)}#midias`; }
    exportPdf() { const params = new URLSearchParams({ foco: this.focusId || '', modo: this.modeValue, acima: this.ancestorRange?.value || '2', abaixo: this.descendantRange?.value || '2' }); window.open(`arvore_pdf.php?${params}`, '_blank', 'noopener'); }
    openImporter() { window.location.href = 'importar.php'; }
    async saveFollowedTree(event) { event.preventDefault(); const form = event.currentTarget; const feedback = form.querySelector('[data-follow-feedback]'); try { const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' }); const result = await response.json(); if (!response.ok || !result.sucesso) throw new Error(result.erro || 'Não foi possível salvar.'); feedback.textContent = 'Atalho salvo.'; form.reset(); setTimeout(() => document.querySelector('#follow-dialog')?.close(), 450); } catch (error) { feedback.textContent = error.message; feedback.className = 'form-feedback is-error'; } }

    zoom(factor, clientX, clientY) { const next = Math.max(0.35, Math.min(1.8, this.transform.scale * factor)); if (clientX !== undefined && clientY !== undefined) { const rect = this.viewport.getBoundingClientRect(); const x = clientX - rect.left; const y = clientY - rect.top; const ratio = next / this.transform.scale; this.transform.x = x - (x - this.transform.x) * ratio; this.transform.y = y - (y - this.transform.y) * ratio; } this.transform.scale = next; this.applyTransform(); }
    fitVisible() { if (!this.world) return; const width = this.world.offsetWidth || 640; const height = this.world.offsetHeight || 460; const availableWidth = Math.max(240, this.viewport.clientWidth - 48); const availableHeight = Math.max(240, this.viewport.clientHeight - 48); this.transform.scale = Math.max(0.35, Math.min(1.12, Math.min(availableWidth / width, availableHeight / height))); this.transform.x = (this.viewport.clientWidth - width * this.transform.scale) / 2; this.transform.y = (this.viewport.clientHeight - height * this.transform.scale) / 2; this.applyTransform(); }
    centerFocus() { const position = this.positions.get(String(this.focusId)); if (!position || !this.viewport) return; this.transform.x = this.viewport.clientWidth / 2 - (position.x + CARD.width / 2) * this.transform.scale; this.transform.y = this.viewport.clientHeight / 2 - (position.y + CARD.height / 2) * this.transform.scale; this.applyTransform(); }
    applyTransform() { if (this.world) this.world.style.transform = `translate3d(${this.transform.x}px, ${this.transform.y}px, 0) scale(${this.transform.scale})`; if (this.zoomLabel) this.zoomLabel.textContent = `${Math.round(this.transform.scale * 100)}%`; }
    startPan(event) { if (event.button !== 0 || event.target.closest('.tree-card, .tree-add-slot, button, a, input, select')) return; this.drag = { x: event.clientX, y: event.clientY, tx: this.transform.x, ty: this.transform.y }; this.viewport.classList.add('is-panning'); this.viewport.setPointerCapture?.(event.pointerId); }
    movePan(event) { if (!this.drag) return; this.transform.x = this.drag.tx + event.clientX - this.drag.x; this.transform.y = this.drag.ty + event.clientY - this.drag.y; this.applyTransform(); }
    endPan() { if (!this.drag) return; this.drag = null; this.viewport.classList.remove('is-panning'); }
    togglePanel() { this.panelOpen = !this.panelOpen; document.body.classList.toggle('tree-panel-closed', !this.panelOpen); const button = document.querySelector('[data-tree-action="toggle-panel"]'); if (button) { button.setAttribute('aria-pressed', String(this.panelOpen)); button.textContent = this.panelOpen ? '‹' : '›'; button.title = this.panelOpen ? 'Ocultar painel lateral' : 'Mostrar painel lateral'; } setTimeout(() => this.fitVisible(), 180); }
    setStatus(text) { if (this.status) this.status.textContent = text; }
    showEmpty(title, message) { if (this.empty) { this.empty.hidden = false; const heading = this.empty.querySelector('[data-empty-title]'); const text = this.empty.querySelector('[data-empty-message]'); if (heading) heading.textContent = title; if (text) text.textContent = message; } }
    hideEmpty() { if (this.empty) this.empty.hidden = true; }
    escape(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])); }
  }

  window.FamilyTreeView = FamilyTreeView;
})();
