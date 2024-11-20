# Arlo for WordPress Plugin

This repository contains the source code for the [Arlo for WordPress](https://wordpress.org/plugins/arlo-training-and-event-management-system/) plugin. The plugin is maintained on GitHub and deployed to the WordPress plugin repository using GitHub Actions. This README serves as a guide for contributors to make changes and deploy the plugin.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Getting Started](#getting-started)
- [Making Changes](#making-changes)
- [Deploying the Plugin](#deploying-the-plugin)
- [Resources](#resources)

## Prerequisites

- [Git](https://git-scm.com/downloads) installed on your local machine
- A [GitHub](https://github.com/) account
- Basic understanding of the [OneFlow](https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow) branching strategy

## Getting Started

1. Fork the repository into your GitHub account.
1. Clone your forked repository onto your local machine using `git clone <repository_url>`.
1. Set up a new remote named `upstream` to track the original repository: `git remote add upstream git@github.com:ArloSoftware/arlowp-plugin.git`.
1. Ensure your fork is up-to-date with the original repository by executing `git pull upstream master` before making any changes.

## Making Changes

1. Create a new feature branch based on the current `master` branch. Name your branch according to the task you're working on, e.g., `git checkout -b feature/shortcode-extension master`.
1. Make your changes in the newly created branch.
1. Commit your changes using descriptive commit messages.
1. Push your branch to your forked repository: `git push origin <branch_name>`.
1. Open a pull request on the original repository to merge your changes into the `master` branch. Make sure to provide a detailed description of your changes.

## Deploying the Plugin

To deploy a new release of the plugin, follow these steps:

1. Create a release branch from the current `master` branch, e.g., `git checkout -b release/4.1.6 master`.
1. Update the version number in the `readme.txt` file and include the changes made in the release.
1. Update the `CHANGELOG.txt` file to include the changes made in the new release.
1. Update the version number in `arlo-for-wordpress.php`.
1. Update the `arlo-for-wordpress-settings.php` file to include the changes made in the new release.
1. Update the version number in the `includes/arlo-version-handler.php` file.
1. Commit your changes with a descriptive commit message.
1. Push the release branch to your forked repository: `git push origin <branch_name>`.
1. Open a pull request on the original repository to merge the release branch into the `master` branch. Provide a detailed description of the release.
1. Once the pull request is approved and merged, create a new release on GitHub by following these steps:
   - Go to the repository's "Releases" tab.
   - Click on "Draft a new release" or "Create a new release."
   - Enter the new version number as the tag, e.g., `v4.1.6`.
   - Select the appropriate target branch, usually `master`.
   - Write a title and release notes, including the changes made in the new release.
   - Click "Publish release."
1. The GitHub Action will automatically deploy the plugin to the WordPress plugin repository upon creating the new release.

## Resources
- [Arlo for WordPress Plugin Page](https://wordpress.org/plugins/arlo-training-and-event-management-system/)
- [Arlo WordPress Plugin Documentation](https://developer.arlo.co/doc/wordpress/index)
- [LICENSE](LICENSE.txt)
