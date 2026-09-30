#!/usr/bin/env node
import * as cdk from 'aws-cdk-lib';
import { readFileSync } from 'fs';
import { join } from 'path';
import { ApplicationAwsStage } from '../lib/app-aws-stage';

// The application name is the vendor part of the root composer.json name — its only source.
const appName: string = JSON.parse(readFileSync(join(__dirname, '../../../composer.json'), 'utf8')).name.split('/')[0];

const app = new cdk.App();

[
	'staging',
	'production',
].forEach((stageName) => {
	const coreStage = new ApplicationAwsStage(app, `${appName}-backend-${stageName}`, {stageName});

	cdk.Tags.of(coreStage).add('project', `${appName}-backend`);
})
