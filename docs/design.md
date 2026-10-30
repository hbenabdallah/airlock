# Airlock: design

Airlock is an MCP server that lets any agent run shell commands on your machine
without trusting the agent with your machine. Claude Code, Zed, VS Code or Cursor
connect to it like to any MCP server; every command they send passes through it.

## The problem

An agent runs commands it decided to run, from text it read. A README, a log, a
web page or a dependency's error message can tell it to run something else, and
it often does. Each client answers this its own way — a list of allowed command
prefixes here, an "allow for this session" there — and none of it travels: the
same user, in another client, starts from nothing.

Airlock moves the answer out of the client and into one server every client can
use: what runs, where it runs, what the user is asked, and what the agent is
allowed to read back.

## What happens to a command

1. **Read.** The command line is parsed the way a shell reads it: simple
   commands, pipes, `&&`, `;`, redirections, substitutions. A grant for
   `make test` covers `make test`, never `make test; rm -rf ~`.
2. **Weighed.** Each part is one of:
   - *plain*: covered by a grant, or harmless to run;
   - *asked every time*: `sudo`, a recursive delete, `git push`, a download piped
     into a shell, one that names a secret file (`.env`, `id_rsa`, `*.pem`), or
     one that cannot be read whole (`$(...)`, a here-document). No grant covers it;
   - *refused*: what the project's policy forbids outright.
3. **Asked.** Anything not covered goes to the user as a form, in their own
   client: what will run, why it is asked, and a choice of *once*, *this
   session*, *always for this project* or *refuse*. A command asked every time
   only offers *once* or *refuse*.
4. **Run.** In a sandbox: bubblewrap on Linux, `sandbox-exec` on macOS. The
   command can write only to the project, `/tmp` and package caches, and cannot
   read Airlock's own files or the user's keys; with `strict`, it has no network.
   Where no sandbox can run, Airlock says so, and asks about every command.
5. **Checked.** Its output goes back to the agent marked as data, not
   instructions, with API keys masked. A classifier reads it first: output that
   looks like an injection is flagged, so the agent and the user both see that
   it tried.

Every decision is appended to an audit log, readable as the MCP resource
`airlock://audit`.

## The protocol

Airlock is written for MCP 2026-07-28. A form is not a request the server sends
mid-call any more: the tool returns an `input_required` result naming the form,
and the client calls again with the answer (*Multi Round-Trip Requests*). Claude
Code speaks this already. The PHP SDK serves the same tool to clients that only
know 2025-11-25, by turning the result into a classic `elicitation/create`
request and running the tool again with the answer; the tool cannot tell which
happened. Both paths are tested.

The state that rides along with the retry comes back from the client, so it is
attacker-controlled: Airlock signs it, and it names the exact command it was
minted for, so an answer for `make test` cannot be replayed to run anything else.

Sampling, which once let a server borrow the client's model, is deprecated since
2026-07-28 in favour of calling a provider directly — which is what Airlock does
through Symfony AI.

## What Symfony AI does

- **Output classification.** A prompt-injection classifier,
  `protectai/deberta-v3-base-prompt-injection-v2`, served by Hugging Face
  Inference Providers through `symfony/ai-platform` and its Hugging Face bridge.
- **Risk in plain words.** For a command asked every time, a small chat model
  writes one line saying what it would do ("deletes build/ and everything in
  it"), shown in the form. Structured output, so the line is a field, not prose
  to parse.

Both are optional. Without a Hugging Face token, the classifier is off and the
form shows the rule that triggered instead; nothing about running a command
depends on a model being reachable.

## Built like it should be

Symfony 8.1, the MCP bundle, PHP 8.5. Every class `final`, value objects
`readonly`, `declare(strict_types=1)` everywhere, PHPStan at its highest level
with strict rules and no baseline, PHP CS Fixer on `@Symfony` and its risky
set, Rector, and one `composer check` that CI runs. PHPUnit: the parser and the
policy by their public API; the server through both protocol eras over stdio;
the Hugging Face calls against recorded responses. And tested by hand in a real
client before each change is merged.

Grants and the audit log are files under the user's configuration directory,
written under a `symfony/lock` lock: two agents can share one Airlock.

## Not in scope

- Being a security boundary against a malicious *user*: Airlock protects a user
  from what their agent read, not a machine from its owner.
- Windows: the sandboxes are Linux's and macOS's.
- Replacing the client's own approvals: Airlock adds a layer every client
  shares; a client may still ask before calling it.

## Milestones (to 22 November 2026, 18:00 CET)

| Dates | What | Done when |
|---|---|---|
| 1–4 Nov | Parser, policy, sandbox, `run_command` | a command runs sandboxed, and the dangerous ones are recognised, under test |
| 5–8 Nov | The form: once / session / always / refuse, signed state, grants, audit log | approved, refused and replayed answers behave, in both protocol eras, and in Claude Code |
| 9–12 Nov | Symfony AI: output classification, risk in plain words, fallbacks | an injected README is flagged in a real session; works with no token |
| 13–16 Nov | CI, README from `git clone` to a running demo in five minutes | a stranger's clone works |
| 17–20 Nov | The 90-second video, polish, repository made public | submitted, with two days to spare |
