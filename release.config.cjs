/* eslint-disable no-template-curly-in-string */
const {
  addChangelogPlugin,
  addCommitAnalyzerPlugin,
  addExecPlugin,
  addGitHubActionsOutputPlugin,
  addGitHubPlugin,
  addGitPlugin,
  addReleaseNotesGeneratorPlugin,
} = require('@myparcel-dev/semantic-release-config/src/plugins');
const {gitPluginDefaults} = require('@myparcel-dev/semantic-release-config/src/plugins/addGitPlugin');
const mainConfig = require('@myparcel-dev/semantic-release-config');

module.exports = {
  ...mainConfig,
  extends: '@myparcel-dev/semantic-release-config',
  branches: [
    {name: 'main'},
    {name: 'beta', prerelease: 'beta', channel: 'beta'},
  ],
  plugins: [
    addCommitAnalyzerPlugin(),
    addGitHubActionsOutputPlugin(),
    addReleaseNotesGeneratorPlugin(),
    addChangelogPlugin(),
    addExecPlugin({
      // Stamp the release version into the extension manifest and build the
      // installable package.
      prepareCmd: 'bin/prepare-release ${nextRelease.version}',
    }),
    addGitHubPlugin({
      assets: [
        {
          // OpenCart derives the extension code from the zip filename, so the
          // published asset must stay literally myparcel.ocmod.zip.
          path: 'dist/myparcel.ocmod.zip',
          name: 'myparcel.ocmod.zip',
          label: 'MyParcel OpenCart ${nextRelease.version} (myparcel.ocmod.zip)',
        },
      ],
    }),
    addGitPlugin({
      ...gitPluginDefaults,
      assets: [
        ...gitPluginDefaults.assets,
        'plugin/extension/myparcel/install.json',
        'README.md',
      ],
    }),
  ],
};
