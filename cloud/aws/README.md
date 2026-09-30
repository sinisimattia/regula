# AWS CDK

This is a TypeScript-based AWS CDK project that describes and builds the necessary stack for the additional services used by the application.

The `cdk.json` file tells the CDK Toolkit how to execute your app.

> **Not deployed, and not in use yet.** Development happens locally only for now: the application
> runs entirely on docker compose (see the root `README.md`). The stacks here are prepared but have
> never been deployed. The hosting stack, meaning what runs the application, its workers and its IAM
> role, will be decided later. Until then nothing here grants the application access to these
> resources.

## Prerequisites

Before working with this project, make sure you have the following installed on your machine:

- [Node.js](https://nodejs.org/) (LTS version recommended)
- [AWS CLI v2](https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html)
- **AWS CDK CLI** (must be installed globally):

  ```bash
  npm install -g aws-cdk
  ```

  > This is required to run the `cdk` command outside of `npx`. The CDK library version used by this project is pinned in `package.json`.

## Configuring your AWS profile

This project uses AWS SSO to authenticate against the AWS account. You only need to configure your profile once.

1. Run the configuration wizard:

   ```bash
   aws configure sso
   ```

2. Provide the following values when prompted:
   - **SSO start URL**: the SSO portal URL of your organization
   - **SSO Region**: the AWS region where SSO is configured
   - **Account / Role**: select the account and role you have been granted access to
   - **CLI default region**: the region you want to operate in
   - **CLI profile name**: a memorable name (e.g. `app-dev`)

3. Verify the profile works:

   ```bash
   aws sso login --profile {your-profile-name}
   aws sts get-caller-identity --profile {your-profile-name}
   ```

4. (Optional) Export the profile in your shell so you don't have to pass `--profile` every time:

   ```bash
   export AWS_PROFILE={your-profile-name}
   ```

## Project setup

All commands below must be run from the `cloud/aws/` folder:

```bash
cd cloud/aws
npm install
```

## Useful commands

### Authentication

| Command | Description |
| --- | --- |
| `aws sso login --profile {your-profile-name}` | Log in to AWS via SSO |

### Development

| Command | Description |
| --- | --- |
| `npm run build` | Compile TypeScript to JavaScript |
| `npm run watch` | Watch for changes and recompile |
| `npm run test` | Run the Jest unit tests |
| `npm run lint` | Fix all linting issues |

### CDK

| Command | Description |
| --- | --- |
| `npx cdk synth` | Emit the synthesized CloudFormation template |
| `npx cdk diff` | Compare the deployed stack with the current state |
| `npx cdk deploy` | Deploy the stack to your default AWS account/region |

## Stacks

`{app}` below is the application name: the vendor part of the root `composer.json` name, which
`bin/app-aws.ts` reads (PHP-22).

Each stage (`staging`, `production`) gets two stacks. Use the **construct path**, not the bare
CloudFormation stack name, when you synth, diff or deploy:

```bash
npx cdk deploy "{app}-backend-staging/main"
npx cdk deploy "{app}-backend-staging/database"
```

| Stack | Resources |
| --- | --- |
| `main` | SQS queues (`default`, `high`, `low`, `default-ordered.fifo` with its dead-letter queue), the log group, the `files` S3 bucket and the `cache` DynamoDB table |
| `database` | Aurora PostgreSQL cluster, its security groups, and the generated credentials secret |

## Database

The `database` stack runs Aurora PostgreSQL: Serverless v2 (0.5–8 ACU) on staging, a provisioned
`db.r8g.xlarge` writer on production. Credentials are generated at deploy time into AWS Secrets
Manager — they are never stored in the repository, and there is no `.env` file to copy them into.

### Reading the stack outputs

```bash
aws cloudformation describe-stacks \
  --stack-name {app}-backend-{stage}-database \
  --region eu-central-1 \
  --query 'Stacks[0].Outputs[].[ExportName,OutputValue]' --output text
```

| Export | Use |
| --- | --- |
| `{app}-backend-{stage}-database-cluster-endpoint` | writer host → `DB_HOST` |
| `{app}-backend-{stage}-database-reader-endpoint` | reader host → `DB_READ_HOST` |
| `{app}-backend-{stage}-database-secret-arn` | credentials secret, see below |
| `{app}-backend-{stage}-database-app-security-group-id` | application security group, attach to the EC2 instance |

### Fetching the credentials

Log in first if your session has expired — see [Configuring your AWS profile](#configuring-your-aws-profile):

```bash
aws sso login --profile {your-profile-name}
export AWS_PROFILE={your-profile-name}
```

Then read the secret named by the `…-secret-arn` output:

```bash
SECRET_ARN=$(aws cloudformation describe-stacks \
  --stack-name {app}-backend-{stage}-database \
  --region eu-central-1 \
  --query "Stacks[0].Outputs[?ExportName=='{app}-backend-{stage}-database-secret-arn'].OutputValue" \
  --output text)

aws secretsmanager get-secret-value \
  --secret-id "$SECRET_ARN" --region eu-central-1 \
  --query SecretString --output text | jq .
```

The secret holds `username`, `password`, `host`, `port`, `dbname` and `engine`, so a single field
comes out with `jq -r '.password'`.

### Application environment variables

Fill these into the Forge environment for the matching server. `DB_PASSWORD` comes from the secret
above; everything else comes from the stack outputs.

```dotenv
DB_CONNECTION=pgsql
DB_HOST=<cluster-endpoint output>
DB_READ_HOST=<reader-endpoint output>
DB_PORT=5432
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=<password field of the secret>
DB_SSLMODE=require
```

`DB_READ_HOST` is what Laravel actually reads for the read/write split. Production currently sets
`DB_HOST_READONLY`, which nothing reads — do not carry that name across, or reads will silently keep
going to the writer with no error.

### Connecting by hand

The Aurora security group accepts 5432 from exactly two sources: the application security group, and
the Client VPN. Connect to the VPN and you can point a SQL client straight at the cluster endpoint.
Until the application security group is attached to the EC2 instance — a manual step, because those
instances are provisioned by Forge and not by CDK — the application itself cannot reach the database,
and the failure looks like a connection timeout rather than an error.
