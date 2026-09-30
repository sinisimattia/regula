import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import { Queue } from 'aws-cdk-lib/aws-sqs';
import { BlockPublicAccess, Bucket, BucketEncryption } from 'aws-cdk-lib/aws-s3';
import { AttributeType, BillingMode, Table } from 'aws-cdk-lib/aws-dynamodb';

export class ApplicationCoreStack extends cdk.Stack {
	constructor(scope: Construct, id: string, props?: cdk.StackProps) {
		super(scope, id, props);

		const name = `${props?.stackName}`;

		// Queues
		const defaultQueue = new Queue(this, `${name}-default`, {
			queueName: `${name}-default`,
			visibilityTimeout: cdk.Duration.minutes(5),
		});

		const highQueue = new Queue(this, `${name}-high`, {
			queueName: `${name}-high`,
			visibilityTimeout: cdk.Duration.minutes(5),
		});

		const lowQueue = new Queue(this, `${name}-low`, {
			queueName: `${name}-low`,
			visibilityTimeout: cdk.Duration.minutes(5),
		});

		const defaultOrderedDlq = new Queue(this, `${name}-default-ordered-dlq.fifo`, {
			queueName: `${name}-default-ordered-dlq.fifo`,
			fifo: true,
			retentionPeriod: cdk.Duration.days(14),
		});

		const defaultOrderedQueue = new Queue(this, `${name}-default-ordered.fifo`, {
			queueName: `${name}-default-ordered.fifo`,
			fifo: true,
			contentBasedDeduplication: false,
			visibilityTimeout: cdk.Duration.minutes(5),
			deadLetterQueue: {
				queue: defaultOrderedDlq,
				maxReceiveCount: 3,
			},
		});

		// Logs
		const defaultLogGroup = new cdk.aws_logs.LogGroup(this, `${name}-logs`, {
			logGroupName: `${name}-logs`,
			retention: cdk.aws_logs.RetentionDays.ONE_YEAR,
		})

		// Files: the application's `s3` disk (FILESYSTEM_DISK=s3, AWS_BUCKET)
		const filesBucket = new Bucket(this, `${name}-files`, {
			bucketName: `${name}-files`,
			blockPublicAccess: BlockPublicAccess.BLOCK_ALL,
			encryption: BucketEncryption.S3_MANAGED,
			enforceSSL: true,
			versioned: true,
			removalPolicy: cdk.RemovalPolicy.RETAIN,
		});

		// Cache: the application's `dynamodb` store (CACHE_STORE=dynamodb, DYNAMODB_CACHE_TABLE).
		// Attribute names are the ones Laravel's DynamoDB store reads by default.
		const cacheTable = new Table(this, `${name}-cache`, {
			tableName: `${name}-cache`,
			partitionKey: { name: 'key', type: AttributeType.STRING },
			timeToLiveAttribute: 'expires_at',
			billingMode: BillingMode.PAY_PER_REQUEST,
			removalPolicy: cdk.RemovalPolicy.DESTROY,
		});

		// Output information relevant to this stack's deployment
		new cdk.CfnOutput(this, `${name}-default-queue-name`, {
			exportName: `${name}-default-queue-name`,
			value: defaultQueue.queueName,
		});
		new cdk.CfnOutput(this, `${name}-high-queue-name`, {
			exportName: `${name}-high-queue-name`,
			value: highQueue.queueName,
		});
		new cdk.CfnOutput(this, `${name}-low-queue-name`, {
			exportName: `${name}-low-queue-name`,
			value: lowQueue.queueName,
		});
		new cdk.CfnOutput(this, `${name}-default-ordered-queue-name`, {
			exportName: `${name}-default-ordered-queue-name`,
			value: defaultOrderedQueue.queueName,
		});
		new cdk.CfnOutput(this, `${name}-default-ordered-dlq-name`, {
			exportName: `${name}-default-ordered-dlq-name`,
			value: defaultOrderedDlq.queueName,
		});
		new cdk.CfnOutput(this, `${name}-default-log-group-name`, {
			exportName: `${name}-default-log-group-name`,
			value: defaultLogGroup.logGroupName,
		});
		new cdk.CfnOutput(this, `${name}-files-bucket-name`, {
			exportName: `${name}-files-bucket-name`,
			value: filesBucket.bucketName,
		});
		new cdk.CfnOutput(this, `${name}-cache-table-name`, {
			exportName: `${name}-cache-table-name`,
			value: cacheTable.tableName,
		});
	}
}
