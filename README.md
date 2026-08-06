# Arlo for WordPress Plugin

This repository contains the source code for the [Arlo for WordPress](https://wordpress.org/plugins/arlo-training-and-event-management-system/) plugin. The plugin is maintained on GitHub and deployed to the WordPress plugin repository using GitHub Actions. This README serves as a guide for contributors to make changes and deploy the plugin.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Getting Started](#getting-started)
- [Making Changes](#making-changes)
- [Release Process](#release-process)
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

## Release Process

This section is for maintainers with write access to the canonical `ArloSoftware/arlowp-plugin` repository. The commands assume that `origin` refers to that repository.

Releases begin with a branch containing staged release code and are published from an immutable Git tag. The pull request merges the staged release branch into `master`; the tag determines the exact source deployed to WordPress.org.

The GitHub Actions workflow files must be present on the default `master` branch before their manual **Run workflow** controls are available. Production workflows use the protected `wordpress-org-production` environment. Its credentials and approval rules are configured in GitHub and are not stored in this repository.

1. **Prepare code to release.** Start from the branch that contains the staged release code, then confirm it is up to date. This is the pull request source branch and must differ from its target branch:

   ```sh
   git switch <staged_release_branch>
   git pull --ff-only origin <staged_release_branch>
   ```

1. **Bump version tags.** Review the release metadata in the staged release commit. The release workflows validate the version declarations automatically; confirm the intended stable `X.Y.Z` version is present and update any stale or missing values if required:

   - `readme.txt`: `Stable tag`
   - `arlo-for-wordpress.php`: plugin header `Version`
   - `includes/arlo-version-handler.php`: `VersionHandler::VERSION`
   - `CHANGELOG.txt`: add a `== vX.Y.Z ==` entry for the release

1. **Raise PR.** Open a pull request from the staged release branch into `master`. The **Validate Release Metadata** workflow checks pull requests into `master` and can also be run manually. **Publish Release to WordPress.org** independently revalidates the version declarations for the selected tag, but intentionally does not require a changelog entry.

1. **Tag release.** After the pull request is approved and merged, create an annotated tag from the approved commit on `master`. The tag determines the exact source published to WordPress.org:

   ```sh
   git switch master
   git pull --ff-only origin master
   git tag -a vX.Y.Z -m "Release vX.Y.Z"
   git push origin vX.Y.Z
   ```

1. **Publish Release to WordPress.org.** Nothing is published to the live WordPress.org SVN repository before this step. In GitHub Actions, run **Publish Release to WordPress.org** and enter `vX.Y.Z`. The workflow validates the selected tag, creates or updates a GitHub Release draft, deploys that tagged source to WordPress.org SVN, uploads the deployed archive, and publishes the GitHub Release only after deployment succeeds. It refuses to republish a version whose WordPress.org SVN tag already exists; use the recovery workflow if a completed deployment needs GitHub Release repair. **Caution:** after this workflow succeeds, the release is live in WordPress.org SVN and available for public WordPress.org distribution.

Do not manually create or publish the GitHub Release before running the workflow.

### Recovery

- Run **Recover WordPress.org Release** after a partial failure. Enter the Git tag version, for example `v5.1.0`, then choose the recovery mode:
   - `deploy`: use when WordPress.org SVN deployment did not complete. The workflow refuses to run if the matching SVN tag already exists.
   - `repair-github-release`: use when WordPress.org SVN deployment completed but the GitHub Release archive upload or publication did not. This rebuilds the archive from the selected Git tag, repairs the GitHub Release, and does not change WordPress.org SVN.
- Run **Revert WordPress.org Release** to revert WordPress.org SVN trunk to an existing release tag, preventing a newer release from being distributed further. Enter the SVN tag version without the Git prefix, for example `5.1.0`. The workflow defaults to a dry run and requires explicit confirmation before it commits any SVN changes.

## Resources
- [Arlo for WordPress Plugin Page](https://wordpress.org/plugins/arlo-training-and-event-management-system/)
- [Arlo WordPress Plugin Documentation](https://developer.arlo.co/doc/wordpress/index)
- [LICENSE](LICENSE.txt)
