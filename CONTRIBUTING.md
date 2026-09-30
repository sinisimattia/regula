# Contributing

Please read and understand the contribution guide before creating an issue or pull request.

## Etiquette

This project is a production backend, and so it must be worked on with the utmost care and consideration.
Speed is not welcome, code quality reigns supreme. As such please follow this document to ensure proper collaboration
with your fellow developers.

The developer that worked on a particular fix/feature is NOT the only one responsible for it, instead him/her and whoever
reviewed it has the responsibility of assuring that everything works correctly and that it doesn't break previous work(s).

Let's keep this codebase clean and understandable. Scrappy code will not be allowed.

## Start developing

| Tool                          | Information                                                                                                                                                                                                                                                                                                                 |
|-------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Docker                        | Used to easily create the development environment, instead of installing requirements on your machine. This will also make sure that you and your fellow developers work on the same exact environment.                                                                                                                     |
| PHPStorm (optional)           | Preferable to other text editors as it is a fully-feature IDE with integration with all the tools of this project.                                                                                                                                                                                                          |
| Visual Studio Code (optional) | With the following extensions: [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug), [PHP Intelephense](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client) and [PHP-CS-Fixer](https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer) |

### Initial setup

First, spin up the Docker environment.

```shell
docker compose up -d --build
```

You should have a service running called `app`. **All subsequent commands will be run inside the `app` service container.**

Run to open app's bash terminal:
```shell
docker compose exec app bash
```

You should be inside the container now. Let's install the dependencies:

```shell
composer install
```

Set the admin user's email in `.env` (the seeder makes this user a Super Admin):

```
ADMIN_EMAIL=your@email.com
```

And migrate:

```shell
php artisan migrate --seed
```

#### Linux Setup (RPM-based distros: Fedora, RHEL, CentOS)

If you're on an RPM-based Linux distribution like Fedora, follow these additional steps:

##### 1. Install Docker (Moby Engine)
```bash
# Install Moby Engine (Fedora's Docker)
sudo dnf install moby-engine docker-compose

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Add your user to docker group
sudo usermod -aG docker $USER

# NOTE: Log out and log back in for group changes to take effect
```

##### 2. Fix SELinux Permissions

Fedora uses SELinux which requires additional configuration for Docker volumes:
```bash
# Navigate to project directory
cd /path/to/project

# Fix SELinux context for the project
sudo chcon -Rt svirt_sandbox_file_t /path/to/project

# Fix database init script permissions
sudo chmod 644 docker/postgres/init-db.sql
```
**Alternative:** Add `:z` flag to volume mounts in `docker-compose.yml`:
```yaml
volumes:
    - ./:/var/www:z
    - ./docker/php/php.ini:/usr/local/etc/php/conf.d/docker-extended.ini:z
```

###### 3. Optional: Troubleshooting Container Permission Errors
```bash
# Fix storage permissions
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

###### 4. Optional: Troubleshooting PostgreSQL Permission Errors
```bash
# Grant database permissions
docker compose exec db psql -U app -d postgres -c 'GRANT ALL PRIVILEGES ON DATABASE app TO app;'
docker compose exec db psql -U app -d postgres -c 'GRANT ALL PRIVILEGES ON DATABASE testing TO app;'
```

The `app` and `testing` databases are created on first boot by
`docker/postgres/init-db.sql`, which only runs while the data volume is empty. If
they are missing, recreate the volume with `docker compose down -v`.

Now we can start developing!

### Running queued jobs

Jobs run inline by default (`QUEUE_DRIVER=sync`), so there is no worker to start. To test real
queueing, set `QUEUE_DRIVER=database` in `.env` and start the worker and the scheduler:

```shell
docker compose --profile workers up -d
```

### Some other useful commands

To check or fix any code style issues run:

```shell
composer lint:check
```

or

```shell
composer lint:fix
```

To run the entire test suite run:

```shell
composer test
```

To run the entire test suite WITH COVERAGE run:

```shell
composer test:coverage
```

## Coding standards

The coding standards live in **[`docs/standards/`](docs/standards/)**. That directory is the single
authority — if something there contradicts a comment, an agent, a README or a habit, it wins.

Start with [`docs/standards/README.md`](docs/standards/README.md): it indexes the files, lists the
non-negotiables, and explains how to change a rule you think is wrong. Rules belonging to one domain are in that domain's own `Docs/` folder; platform-wide business rules,
once there are any, go in `docs/business-logic/`.

Every rule has a permanent ID (`DA-03`, `ENT-04`, `GIT-06`). Cite it in review comments so a
disagreement is a lookup rather than an argument.

## Working on and submitting your changes

- Pull `main`, then open a branch named by work type — `feat/<KEY>-<slug>`,
  `fix/<KEY>-<slug>`, `chore/<slug>` or `refactor/<slug>`. Dependent work stacks on the branch
  it depends on, not on `main`. See [`docs/standards/git.md`](docs/standards/git.md).
- Open a pull request with a description of your implementation and add code reviewers.
- If the code review is not successful implement the changes and request it again.
- If the code review is successful (all reviewers approved it) merge it with `--squash` strategy.

Before submitting a pull request:
- Check that the coding standards have been respected
- Check that the fix/feature is tested with a decent amount of coverage.

## Requirements

If the project maintainer has any additional requirements, you will find them listed here.

Code style, typing, architecture, testing and git conventions are all in
[`docs/standards/`](docs/standards/) — they are not repeated here, so there is only ever one copy to
keep current.

What remains, because it is about conduct rather than code:

- **English only!** - The only allowed language for code, comments, documentation and commits is English.
- **Add tests!** - Your patch won't be accepted if it lacks proper tests. See [`docs/standards/testing.md`](docs/standards/testing.md).
- **Document any change in behaviour** - The domain's `README.md` and `Docs/` are updated as part of the change that altered the behaviour, not afterwards.
- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.
- **Leave it better than you found it** - Bring what you touched up to standard. If the fix would spill into files your change doesn't already touch, open a Technical Task instead of growing the pull request. See [`docs/standards/git.md`](docs/standards/git.md).

## Working with AI assistants

Much of this codebase is now written with AI assistance. That is fine, and the rules below are what
keep it fine.

### What's in `.claude/`

- **`agents/`** — subject-matter specialists. Each one understands an area (HTTP, domains, the database, tests, Filament, Docker, tasks) and can be used for **any** phase of
  work: building, reviewing, exploring, debugging, or just answering a question. They are not
  "code generators" — asking one how something works is a legitimate use.
- **`skills/`** — workflows you invoke by name:
  - `/audit` — review a branch, a domain, a path, or anything you describe, against the standards.
    Reports findings, never rewrites your code.
  - `/ship` — takes a ticket from intake through questions, planning, implementation and, when the
    change is delicate, a staged release.
  - `coding-standards` — pulls the canon into a session.
  - `/review-pr` — reviews a GitHub pull request.
- **hooks** — automation that runs at fixed points, such as prompting for a standards review when a
  turn has changed PHP.

### What you get automatically, and what you don't

Everything in `.claude/` is committed, so cloning this repository gives you the specialists, the
skills, and the hook that reminds you to review changed PHP. `CLAUDE.md` points at the standards on
its own, and CI runs `composer lint:check`, `composer insights` and `composer canon:check` on every
pull request whether or not anyone remembers to.

That includes the `superpowers` plugin, which provides the brainstorming and planning workflow
`/ship` builds on. It is declared in `.claude/settings.json`, so you do not install it by hand — it
activates once you trust the folder, which Claude Code asks about the first time you open the
repository.

If Claude ever reports it as not installed, it prints the `claude plugin install` command to run.
And if it is genuinely unavailable, `/ship` says so once and carries out the stages itself rather
than failing: the gates in `docs/standards/` are what matter, and none of them depend on a plugin.

### The rules

**The standards outrank the assistant.** [`docs/standards/`](docs/standards/) is the authority. If an
assistant produces something that diverges from it, that is a pull request rejection — not a new
precedent, and not evidence that the rule should change. (If you genuinely think the rule is wrong,
[`docs/standards/README.md`](docs/standards/README.md) explains how to change it. Do that instead of
working around it.)

**You own what you submit.** The *Etiquette* section above already says the author and the reviewer
share responsibility for a change. Nothing about that changes when an assistant wrote the code:
submitting it means you have read it and understood it. "The AI wrote it that way" is not a review
response.

**Point it at the rules, don't let it guess.** Give an assistant the relevant standards file for the
work. Left to infer structure from whatever code happens to be nearby, it will reproduce whatever
that code does — including its mistakes. That is exactly how a domain drifts away from every other one.

**Ambiguity is a question, not a guess.** If a ticket doesn't say what should happen in some case, the
answer is to ask, not to pick something plausible and bury it in an implementation.

**Happy coding**!

