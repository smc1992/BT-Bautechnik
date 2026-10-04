export function createDailyLogDraft(wire, userId, storage = localStorage) {
    return {
        state: 'Entwurf', dirty: false, timer: null, activeProject: null,
        fields: ['projectId', 'contactId', 'date', 'weather', 'temperature', 'workersCount', 'workPerformed', 'specialOccurrences'],
        key(projectId) { return `bt:daily-log-draft:v1:${userId}:${projectId}`; },
        init() {
            this.activeProject = wire.projectId;
            this.restore();
            this.$el.addEventListener('input', event => this.changed(event));
            this.$el.addEventListener('change', event => this.changed(event));
            this.flushHandler = () => { if (this.dirty) this.persist(); };
            window.addEventListener('pagehide', this.flushHandler);
        },
        changed(event) {
            const model = [...event.target.attributes].find(attribute => attribute.name.startsWith('wire:model'))?.value;
            if (!this.fields.includes(model)) return;
            if (model === 'projectId') {
                clearTimeout(this.timer);
                if (this.dirty) this.persist();
                this.activeProject = wire.projectId; this.dirty = false;
                wire.set('contactId', '', false); wire.set('workPerformed', '', false); wire.set('specialOccurrences', '', false);
                this.$nextTick(() => this.restore()); return;
            }
            this.dirty = true; this.state = 'Entwurf';
            clearTimeout(this.timer); this.timer = setTimeout(() => this.persist(), 350);
        },
        persist() {
            const values = {};
            this.fields.forEach(field => { values[field] = wire[field]; });
            values.projectId = this.activeProject;
            try { storage.setItem(this.key(this.activeProject), JSON.stringify({ values, savedAt: Date.now() })); this.state = 'Lokal gespeichert'; }
            catch { this.state = 'Lokales Speichern fehlgeschlagen'; }
        },
        restore() {
            this.state = 'Entwurf';
            try {
                const draft = JSON.parse(storage.getItem(this.key(this.activeProject)) || 'null');
                if (!draft?.values || !Number.isFinite(draft.savedAt) || Date.now() - draft.savedAt > 7 * 86400000) { if (draft) storage.removeItem(this.key(this.activeProject)); return; }
                this.fields.filter(field => field !== 'projectId').forEach(field => { if (Object.hasOwn(draft.values, field)) wire.set(field, draft.values[field], false); });
                this.state = 'Lokal gespeichert · Entwurf wiederhergestellt';
            } catch { this.state = 'Entwurf konnte nicht wiederhergestellt werden'; }
        },
        discard() {
            clearTimeout(this.timer); this.dirty = false;
            try { storage.removeItem(this.key(this.activeProject)); } catch { this.state = 'Entwurf konnte nicht gelöscht werden'; return; }
            wire.set('workPerformed', '', false); wire.set('specialOccurrences', '', false); this.state = 'Entwurf verworfen';
        },
        destroy() {
            clearTimeout(this.timer); if (this.dirty) this.persist();
            window.removeEventListener('pagehide', this.flushHandler);
        }
    };
}
