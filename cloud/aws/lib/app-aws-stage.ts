import * as cdk from 'aws-cdk-lib';
import { Construct } from 'constructs';
import { ApplicationCoreStack } from './stacks/core/app-core-stack';
import { ApplicationDatabaseStack } from './stacks/database/app-database-stack';

export class ApplicationAwsStage extends cdk.Stage {
	constructor(scope: Construct, id: string, props?: cdk.StageProps) {
		super(scope, id, props);

		new ApplicationCoreStack(this, 'main', {
			stackName: `${id}-main`,
		});

		new ApplicationDatabaseStack(this, 'database', {
			stackName: `${id}-database`,
			isProduction: id.endsWith('-production'),
		});
	}
}
