#!/usr/bin/env node
// Weekly check of the plugin's wordpress.org listing. Node 22, no dependencies.
//
//   node .github/scripts/check-wordpress-org.mjs [--issue] [--slug other-plugin]
//
// --slug points the check at another plugin, to see the listed and closed
// cases locally.
//
// Issues it keeps, one per topic:
//   wporg-listed   opened once, the first time wordpress.org lists the plugin
//   wporg-version  open while the wordpress.org version and this repository differ
//   wporg-closed   open while wordpress.org shows the plugin as closed
// Without --issue the would-be issues are printed.

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { githubClient, findIssue, upsertIssue, closeIssue, runUrl } from './github-issue.mjs';

export const SLUG = 'moonetix-offerwall';
const apiUrl = (slug) => `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=${slug}&request[fields][sections]=0&request[fields][description]=0&request[fields][reviews]=0`;
const pageUrl = (slug) => `https://wordpress.org/plugins/${slug}/`;

export function repoVersions(pluginPhp, readmeTxt) {
  return {
    header: /^[\s*]*Version:\s*(\S+)/m.exec(pluginPhp)?.[1] ?? null,
    stableTag: /^Stable tag:\s*(\S+)/im.exec(readmeTxt)?.[1] ?? null,
  };
}

// listed | not_listed | closed, from the plugins API answer (any HTTP status).
export function listingState(json, slug = SLUG) {
  if (json?.closed || json?.error === 'closed') return { state: 'closed', since: json.closed_date ?? null, reason: json.reason_text ?? json.reason ?? null };
  if (json?.slug === slug && json?.version) return { state: 'listed', version: String(json.version), updated: json.last_updated ?? null, installs: json.active_installs ?? null };
  return { state: 'not_listed' };
}

// The issue actions for one check, kept pure so the decisions can be tested.
export function plan(listing, repo, run, slug = SLUG) {
  const PAGE = pageUrl(slug);
  const actions = [];
  if (listing.state === 'closed') {
    actions.push({ op: 'upsert', topic: 'wporg-closed', title: 'Plugin closed on wordpress.org', body: `wordpress.org shows ${PAGE} as closed${listing.since ? ` since ${listing.since}` : ''}${listing.reason ? `. Reason given: ${listing.reason}` : ''}.\n\nNew sites cannot install it from the plugin directory until it is reopened. Check the plugins team email for the plugin owner account.\n\nLast checked: ${run}` });
    return actions;
  }
  actions.push({ op: 'close', topic: 'wporg-closed', comment: listing.state === 'listed' ? 'wordpress.org lists the plugin again.' : undefined });
  if (listing.state === 'not_listed') return actions;

  actions.push({ op: 'once', topic: 'wporg-listed', title: 'Listed on wordpress.org', body: `wordpress.org now lists the plugin at ${PAGE}, version ${listing.version}.\n\nWorth doing now: link the directory page from README.md, add the wordpress.org version and installs badges, and point the docs install steps at the directory.\n\nLast checked: ${run}` });
  if (listing.version !== repo.header) {
    const ahead = repo.header && listing.version && listing.version.localeCompare(repo.header, undefined, { numeric: true }) > 0;
    const why = ahead
      ? 'A release reached the wordpress.org SVN repository without being committed here. Bring this repository in line with it.'
      : 'This repository has changes that are not deployed to the wordpress.org SVN repository yet. Tag them in SVN when the release is ready, with the same version in the plugin header and the readme Stable tag.';
    actions.push({ op: 'upsert', topic: 'wporg-version', title: `wordpress.org has ${listing.version}, this repository has ${repo.header}`, body: `${why}\n\n- wordpress.org: ${listing.version} (${PAGE})\n- Plugin header in moonetix-offerwall.php: ${repo.header}\n- Stable tag in readme.txt: ${repo.stableTag}\n\nThis issue closes itself once both versions match.\n\nLast checked: ${run}` });
  } else {
    actions.push({ op: 'close', topic: 'wporg-version', comment: `Both are on ${repo.header} now.` });
  }
  return actions;
}

async function main() {
  const repo = repoVersions(readFileSync('moonetix-offerwall.php', 'utf8'), readFileSync('readme.txt', 'utf8'));
  if (!repo.header) throw new Error('no Version: header in moonetix-offerwall.php');
  if (repo.stableTag && repo.stableTag !== repo.header) console.log(`Note: readme.txt Stable tag ${repo.stableTag} differs from the plugin header ${repo.header}.`);

  const args = process.argv.slice(2);
  const slug = args.includes('--slug') ? args[args.indexOf('--slug') + 1] : SLUG;
  const res = await fetch(apiUrl(slug), { headers: { 'user-agent': 'moonetix-offerwall-wordpress listing check (+https://github.com/moonetix/moonetix-offerwall-wordpress)' }, signal: AbortSignal.timeout(30000) });
  const text = await res.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch {
    throw new Error(`wordpress.org API answered HTTP ${res.status} with no JSON`);
  }
  if (res.status >= 500) throw new Error(`wordpress.org API answered HTTP ${res.status}`);
  const listing = listingState(json, slug);
  console.log(`wordpress.org: ${listing.state}${listing.version ? ` ${listing.version}` : ''}; repository ${repo.header}`);

  const request = args.includes('--issue') ? githubClient() : null;
  if (args.includes('--issue') && !request) throw new Error('--issue needs GITHUB_TOKEN and GITHUB_REPOSITORY');

  for (const a of plan(listing, repo, runUrl(), slug)) {
    if (a.op === 'close') await closeIssue(request, a);
    else if (a.op === 'upsert') await upsertIssue(request, a);
    else if (a.op === 'once') {
      // Opened a single time, ever: once a human closes it, it stays closed.
      if (request && (await findIssue(request, a.topic, 'all'))) continue;
      await upsertIssue(request, a);
    }
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  main().catch((err) => {
    console.error(err.message);
    process.exit(1);
  });
}
