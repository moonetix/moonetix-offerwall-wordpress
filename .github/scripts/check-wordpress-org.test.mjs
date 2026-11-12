import { test } from 'node:test';
import assert from 'node:assert/strict';
import { repoVersions, listingState, plan } from './check-wordpress-org.mjs';

const repo = { header: '1.0.0', stableTag: '1.0.0' };
const ops = (actions) => actions.map((a) => `${a.op} ${a.topic}`);

test('reads the plugin header and the readme Stable tag', () => {
  assert.deepEqual(repoVersions('<?php\n/**\n * Plugin Name: X\n * Version:           1.2.3\n */', '=== X ===\nStable tag: 1.2.3\n'), { header: '1.2.3', stableTag: '1.2.3' });
});

test('maps the three API answers', () => {
  assert.equal(listingState({ error: 'Plugin not found.' }).state, 'not_listed');
  assert.deepEqual(listingState({ error: 'closed', closed: true, closed_date: '2026-12-01', reason_text: 'Guideline Violation' }), { state: 'closed', since: '2026-12-01', reason: 'Guideline Violation' });
  assert.equal(listingState({ slug: 'moonetix-offerwall', version: '1.0.0' }).version, '1.0.0');
});

test('not listed yet: nothing is opened', () => {
  assert.deepEqual(ops(plan({ state: 'not_listed' }, repo, 'r')), ['close wporg-closed']);
});

test('listed with the same version: announce once, no version issue', () => {
  assert.deepEqual(ops(plan({ state: 'listed', version: '1.0.0' }, repo, 'r')), ['close wporg-closed', 'once wporg-listed', 'close wporg-version']);
});

test('listed with a different version: version issue in either direction', () => {
  const behind = plan({ state: 'listed', version: '1.0.1' }, repo, 'r').find((a) => a.topic === 'wporg-version');
  assert.equal(behind.op, 'upsert');
  assert.equal(behind.title, 'wordpress.org has 1.0.1, this repository has 1.0.0');
  assert.match(behind.body, /without being committed here/);
  const ahead = plan({ state: 'listed', version: '1.0.0' }, { header: '1.0.10', stableTag: '1.0.10' }, 'r').find((a) => a.topic === 'wporg-version');
  assert.match(ahead.body, /not deployed to the wordpress\.org SVN repository yet/);
});

test('closed: one closed issue and nothing else', () => {
  const actions = plan({ state: 'closed', since: '2026-12-01', reason: 'Author Request' }, repo, 'r');
  assert.deepEqual(ops(actions), ['upsert wporg-closed']);
  assert.match(actions[0].body, /since 2026-12-01\. Reason given: Author Request\./);
});
