import test from 'node:test';
import assert from 'node:assert/strict';
import { createDailyLogDraft } from '../../resources/js/daily-log-draft.js';
const storage = () => { const values = new Map(); return { getItem: key => values.get(key) ?? null, setItem: (key,value) => values.set(key,value), removeItem: key => values.delete(key) }; };
function draft(userId, projectId, store) {
    const wire = { projectId, contactId: '', date: '2026-10-04', weather: 'Sonnig', workersCount: 2, workPerformed: '', specialOccurrences: '', set(field,value) { this[field] = value; } };
    const state = createDailyLogDraft(wire,userId,store); state.activeProject = projectId; state.$nextTick = callback => callback();
    return {wire,state};
}
test('drafts restore after reopening and stay isolated by user and project', () => {
    const store=storage(); const first=draft(1,'north',store); first.wire.workPerformed='Dacharbeiten'; first.state.persist();
    const same=draft(1,'north',store);same.state.restore();assert.equal(same.wire.workPerformed,'Dacharbeiten');
    for (const [user,project] of [[2,'north'],[1,'south']]) {const other=draft(user,project,store);other.state.restore();assert.equal(other.wire.workPerformed,'');}
});
test('changing project flushes pending edits to the previous project and clears the new form', () => {
    const store=storage();const {wire,state}=draft(1,'north',store);wire.workPerformed='Nord Bericht';state.dirty=true;wire.projectId='south';
    state.changed({target:{attributes:[{name:'wire:model',value:'projectId'}]}});
    assert.equal(JSON.parse(store.getItem(state.key('north'))).values.workPerformed,'Nord Bericht');
    assert.equal(wire.workPerformed,'');assert.equal(store.getItem(state.key('south')),null);
});
test('expired drafts are removed and storage failures do not claim success', () => {
    const store=storage();const {state}=draft(1,'north',store);store.setItem(state.key('north'),JSON.stringify({savedAt:Date.now()-8*86400000,values:{workPerformed:'old'}}));state.restore();assert.equal(store.getItem(state.key('north')),null);
    const failing=draft(1,'north',{setItem(){throw new Error('Quota');}});failing.state.persist();assert.equal(failing.state.state,'Lokales Speichern fehlgeschlagen');
});
test('discard removes only the selected draft and clears its content', () => {
    const store=storage();const {state,wire}=draft(1,'north',store);wire.workPerformed='Text';state.persist();store.setItem(state.key('south'),'other');state.discard();
    assert.equal(store.getItem(state.key('north')),null);assert.equal(store.getItem(state.key('south')),'other');assert.equal(wire.workPerformed,'');
});
