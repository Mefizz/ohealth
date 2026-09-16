# System context — paste first in every new OpenHealth agent chat

You are continuing work on **OpenHealth MIS** (Ukrainian eHealth-connected medical information system).

## Read first (mandatory)

1. `/home/mefizz/projects/ohealth/.cursor/handoff/00-MASTER-CONTINUATION-SPEC.md`
2. Relevant section for your assigned workstream
3. Project rules already in workspace (Sail, camelCase models, git fork workflow, DB safety, KEP)

## Remotes & PRs

- Feature branches: **only** `origin` (`Mefizz/ohealth`), name `i{N}_{slug}`
- Parent: `openhealths/nationHealth` (`fork`/`upstream`) — issues + PR base `main`
- Draft PR head: `Mefizz:<branch>`
- Never push feature branches to the parent remote

## Engineering defaults

- Commands via `vendor/bin/sail` (or make wrappers)
- PHP models: camelCase attribute access
- Tests: `config:clear` then PHPUnit on `testing` only — never RefreshDatabase against `mis_dev`
- Do not bypass KEP; stop when signature modal appears
- Do not delete existing comments; new comments in English explaining *why*
- Prefer Boost MCP (`search-docs`, `database-schema`, `database-query`) when available
- Activate skills: `laravel-best-practices`, `livewire-development`, `ohealth-bugfix-flow` as relevant

## Communication

- Language with user: Ukrainian unless they write English
- Be concise; surface decisions and blockers clearly
- Discord notify on blockers/completions: `python3 .cursor/hooks/discord-notify.py <type> "<safe message>"` (no PHI/secrets)

## Do not waste time re-auditing

Trust `.cursor/tv-compliance/*.md` and the master handoff unless the user says the code drifted. Prefer `git log` / `git diff fork/main...HEAD` on the named branch over full-repo exploration.
