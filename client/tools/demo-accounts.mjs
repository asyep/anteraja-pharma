import fs from 'node:fs';

export function readDemoAccounts() {
  // node-only helper: credentials stay out of the vue/browser bundle.
  const source = fs.readFileSync(new URL('../../server/database/seeders/DatabaseSeeder.php', import.meta.url), 'utf8');
  const block = source.match(/public const DEMO_PASSWORDS = \[([\s\S]*?)\];/)?.[1];
  const people = source.match(/public const DEMO_USERS = \[([\s\S]*?)\];/)?.[1];
  if (!block || !people) throw new Error('Demo credentials are missing from DatabaseSeeder.php.');
  const passwords = Object.fromEntries([...block.matchAll(/'([a-z]+)'\s*=>\s*'([^']+)'/g)].map(m => [m[1], m[2]]));
  const users = [...people.matchAll(/\['([^']+)',\s*'([^']+)',\s*'([^']+)'\]/g)].map(m => ({ role: m[1], name: m[2], email: m[3], password: passwords[m[1]] }));
  if (Object.keys(passwords).length !== 4 || !users.length || users.some(u => !u.password)) throw new Error('Invalid demo credential table.');
  return { passwords, users };
}
