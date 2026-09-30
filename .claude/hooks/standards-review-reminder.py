#!/usr/bin/env python3
"""Stop hook: remind that changed PHP has not been reviewed against the standards.

Reminds once per turn and never blocks twice — `stop_hook_active` guarantees the second
stop goes through, so this can't trap a session. It is deliberately quiet: it says nothing
unless PHP under app/ actually changed, because a hook that fires on every turn gets
disabled, and a disabled hook reviews nothing.

Disable by removing the Stop entry from .claude/settings.json.
"""
import json
import subprocess
import sys

TRIVIAL_LINE_BUDGET = 12


def changed_php_files() -> list[str]:
    try:
        out = subprocess.run(
            ['git', 'status', '--porcelain', '--', 'app/', 'config/', 'routes/', 'database/'],
            capture_output=True, text=True, timeout=10,
        ).stdout
    except Exception:
        return []
    files = []
    for line in out.splitlines():
        path = line[3:].strip().split(' -> ')[-1]
        if path.endswith('.php'):
            files.append(path)
    return files


def changed_line_count() -> int:
    try:
        out = subprocess.run(
            ['git', 'diff', '--numstat', '--', 'app/', 'config/', 'routes/', 'database/'],
            capture_output=True, text=True, timeout=10,
        ).stdout
    except Exception:
        return 0
    total = 0
    for line in out.splitlines():
        parts = line.split('\t')
        if len(parts) == 3:
            for n in parts[:2]:
                if n.isdigit():
                    total += int(n)
    return total


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except Exception:
        return 0

    # Never block a second time: this is what makes the hook loop-proof.
    if payload.get('stop_hook_active'):
        return 0

    files = changed_php_files()
    if not files:
        return 0

    # A comment, a typo, a renamed constant: not worth a review pass.
    if changed_line_count() <= TRIVIAL_LINE_BUDGET:
        return 0

    listed = '\n'.join(f'  - {f}' for f in files[:12])
    more = f'\n  ... and {len(files) - 12} more' if len(files) > 12 else ''

    reason = (
        f'{len(files)} PHP file(s) changed this turn and have not been reviewed against the '
        f'standards:\n{listed}{more}\n\n'
        'Before finishing:\n'
        '  1. Run `composer lint:fix` (never Pint).\n'
        '  2. Run `code-standards-specialist` on the changed files.\n'
        '  3. Run `test-specialist` if this was a feature or a fix.\n'
        '  4. Check the domain\'s README.md and Docs/ still describe reality (DA-18).\n\n'
        'If the change is trivial and mechanical, or you have already done this, say so and stop.'
    )

    print(json.dumps({'decision': 'block', 'reason': reason}))
    return 0


if __name__ == '__main__':
    sys.exit(main())
