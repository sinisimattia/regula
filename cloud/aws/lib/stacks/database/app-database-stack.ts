import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import { InstanceClass, InstanceSize, InstanceType, Peer, Port, SecurityGroup, Vpc } from 'aws-cdk-lib/aws-ec2';
import { Key } from 'aws-cdk-lib/aws-kms';
import {
	AuroraPostgresEngineVersion,
	ClusterInstance,
	Credentials,
	DatabaseCluster,
	DatabaseClusterEngine,
	ParameterGroup,
	PerformanceInsightRetention,
	SubnetGroup,
} from 'aws-cdk-lib/aws-rds';

export interface ApplicationDatabaseStackProps extends cdk.StackProps {
	readonly isProduction: boolean;
}

export class ApplicationDatabaseStack extends cdk.Stack {
	constructor(scope: Construct, id: string, props: ApplicationDatabaseStackProps) {
		super(scope, id, props);

		const name = `${props.stackName}`;

		// Network
		const vpc = Vpc.fromVpcAttributes(this, `${name}-vpc`, {
			vpcId: 'vpc-020525db098384546',
			availabilityZones: [
				'eu-central-1a',
				'eu-central-1b',
				'eu-central-1c',
			],
			privateSubnetIds: [
				'subnet-0ad45639f37eabeb8',
				'subnet-088964bf97e7fa33b',
				'subnet-038a4ea417a2fb0d4',
			],
		});

		const subnetGroup = new SubnetGroup(this, `${name}-subnet-group`, {
			vpc,
			vpcSubnets: { subnets: vpc.privateSubnets },
			subnetGroupName: `${name}-subnets`,
			description: `${name} private subnets`,
		});

		// Security groups
		const applicationSecurityGroup = new SecurityGroup(this, `${name}-app`, {
			vpc,
			securityGroupName: `${name}-app`,
			description: `${name} application instances`,
		});

		const auroraSecurityGroup = new SecurityGroup(this, `${name}-aurora`, {
			vpc,
			securityGroupName: `${name}-aurora`,
			description: 'Aurora PostgreSQL cluster',
			allowAllOutbound: false,
		});

		auroraSecurityGroup.addIngressRule(applicationSecurityGroup, Port.tcp(5432), 'Application instances');
		auroraSecurityGroup.addIngressRule(
			Peer.securityGroupId('sg-077fe510f4a011589'),
			Port.tcp(5432),
			'Client VPN',
		);

		// Database
		const engine = DatabaseClusterEngine.auroraPostgres({
			version: AuroraPostgresEngineVersion.of('17.9', '17'),
		});

		const writerParameterGroup = new ParameterGroup(this, `${name}-writer-parameters`, {
			engine,
			description: `${name} writer instance parameters`,
			parameters: {
				log_min_duration_statement: '1000',
				log_connections: '1',
				log_disconnections: '1',
			},
		});

		const writer = props.isProduction
			? ClusterInstance.provisioned('writer', {
				instanceType: InstanceType.of(InstanceClass.R8G, InstanceSize.XLARGE),
				parameterGroup: writerParameterGroup,
				performanceInsightRetention: PerformanceInsightRetention.DEFAULT,
			})
			: ClusterInstance.serverlessV2('writer', {
				parameterGroup: writerParameterGroup,
				performanceInsightRetention: PerformanceInsightRetention.DEFAULT,
			});

		const cluster = new DatabaseCluster(this, `${name}-cluster`, {
			engine,
			clusterIdentifier: name,
			writer,
			...(props.isProduction ? {} : {
				serverlessV2MinCapacity: 0.5,
				serverlessV2MaxCapacity: 8,
			}),
			defaultDatabaseName: 'app',
			credentials: Credentials.fromGeneratedSecret('app'),
			storageEncryptionKey: Key.fromKeyArn(this, `${name}-key`, 'arn:aws:kms:eu-central-1:906798240612:key/68fe8e76-a578-4875-a84a-d451721e6a72'),
			backup: {
				retention: cdk.Duration.days(props.isProduction ? 30 : 7),
			},
			deletionProtection: props.isProduction,
			removalPolicy: cdk.RemovalPolicy.RETAIN,
			cloudwatchLogsExports: ['postgresql'],
			vpc,
			subnetGroup,
			securityGroups: [auroraSecurityGroup],
		});

		// Output information relevant to this stack's deployment
		new cdk.CfnOutput(this, `${name}-cluster-endpoint`, {
			exportName: `${name}-cluster-endpoint`,
			value: cluster.clusterEndpoint.hostname,
		});
		new cdk.CfnOutput(this, `${name}-reader-endpoint`, {
			exportName: `${name}-reader-endpoint`,
			value: cluster.clusterReadEndpoint.hostname,
		});
		new cdk.CfnOutput(this, `${name}-secret-arn`, {
			exportName: `${name}-secret-arn`,
			value: cluster.secret!.secretArn,
		});
		new cdk.CfnOutput(this, `${name}-app-security-group-id`, {
			exportName: `${name}-app-security-group-id`,
			value: applicationSecurityGroup.securityGroupId,
		});
	}
}
