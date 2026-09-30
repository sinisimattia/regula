import eslint from '@eslint/js';
import tseslint from 'typescript-eslint';

export default tseslint.config(
	{
		// Build output of `npm run build`, git-ignored
		ignores: ['**/*.js', '**/*.d.ts', 'cdk.out/**', 'node_modules/**'],
	},
	eslint.configs.recommended,
	tseslint.configs.recommended,
	{
		rules: {
			'@typescript-eslint/no-unused-vars': 'off',
			'indent': ['warn', 'tab'],
			'quotes': ['warn', 'single'],
		}
	}
);
