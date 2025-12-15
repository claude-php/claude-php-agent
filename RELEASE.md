# Release Process

This document describes the release process for maintainers.

## Prerequisites

- Write access to the repository
- Packagist account with access to the package
- All tests passing on main branch
- All required reviews approved

## Release Checklist

### 1. Prepare the Release

- [ ] Ensure all intended changes are merged to `main`
- [ ] All CI checks are passing
- [ ] Update version number in relevant files
- [ ] Update CHANGELOG.md with release notes
- [ ] Update UPGRADING.md if there are breaking changes

### 2. Update Documentation

- [ ] Review and update README.md if needed
- [ ] Ensure all new features are documented
- [ ] Update code examples if APIs changed
- [ ] Review and update migration guides

### 3. Run Quality Checks

```bash
# Run full test suite
composer test:coverage

# Run static analysis
composer analyse

# Check code style
composer format:check

# Run all checks
composer check:full
```

### 4. Version Bump

Update the version in composer.json if needed (typically handled by Packagist):

```bash
# For patch release (0.1.0 -> 0.1.1)
# Bug fixes only

# For minor release (0.1.0 -> 0.2.0)
# New features, backwards compatible

# For major release (0.1.0 -> 1.0.0)
# Breaking changes
```

### 5. Update CHANGELOG.md

Move all changes from `[Unreleased]` to a new version section:

```markdown
## [0.2.0] - 2025-12-16

### Added
- New feature descriptions

### Changed
- Changed feature descriptions

### Deprecated
- Deprecated features

### Removed
- Removed features

### Fixed
- Bug fixes

### Security
- Security fixes
```

### 6. Commit Changes

```bash
git add CHANGELOG.md UPGRADING.md
git commit -m "chore: prepare release v0.2.0"
git push origin main
```

### 7. Create Git Tag

```bash
# Create annotated tag
git tag -a v0.2.0 -m "Release version 0.2.0"

# Push tag to GitHub
git push origin v0.2.0
```

### 8. Create GitHub Release

1. Go to https://github.com/claude-php/agent/releases/new
2. Select the tag you just created
3. Set release title: `v0.2.0`
4. Copy release notes from CHANGELOG.md
5. Attach any relevant files if needed
6. Mark as pre-release if appropriate
7. Publish release

### 9. Verify Packagist

Packagist should automatically detect the new tag and update the package. Verify:

1. Go to https://packagist.org/packages/claude-php/agent
2. Ensure the new version appears
3. Check that the package details are correct

If auto-update is not working, manually trigger an update on Packagist.

### 10. Announce the Release

- [ ] Update project website (if applicable)
- [ ] Announce on social media
- [ ] Update any related repositories
- [ ] Notify major users of breaking changes

## Version Numbering

Follow [Semantic Versioning](https://semver.org/):

- **MAJOR** (X.0.0): Incompatible API changes
- **MINOR** (0.X.0): Add functionality in a backwards compatible manner
- **PATCH** (0.0.X): Backwards compatible bug fixes

### Pre-release Versions

For pre-release versions, use suffixes:

- `v1.0.0-alpha.1` - Alpha release
- `v1.0.0-beta.1` - Beta release
- `v1.0.0-rc.1` - Release candidate

## Hotfix Process

For urgent bug fixes that need immediate release:

1. Create hotfix branch from latest tag:
   ```bash
   git checkout -b hotfix/v0.1.1 v0.1.0
   ```

2. Make the fix and commit:
   ```bash
   git commit -m "fix: critical bug description"
   ```

3. Update CHANGELOG.md with hotfix notes

4. Merge to main:
   ```bash
   git checkout main
   git merge hotfix/v0.1.1
   git push origin main
   ```

5. Tag and release as normal (steps 7-10)

6. Delete hotfix branch:
   ```bash
   git branch -d hotfix/v0.1.1
   ```

## Rollback Process

If a release has critical issues:

1. **Document the issue** in GitHub
2. **Create a hotfix** following the process above
3. **Mark the broken release** as deprecated on Packagist
4. **Communicate** with users about the issue

## Post-Release

After each release:

- [ ] Monitor GitHub issues for release-related problems
- [ ] Watch Packagist download stats
- [ ] Gather feedback from users
- [ ] Plan next release based on feedback

## Branch Strategy

- `main` - Stable, production-ready code
- `develop` - Integration branch for next release (if using)
- `feature/*` - Feature branches
- `bugfix/*` - Bug fix branches
- `hotfix/*` - Urgent hotfix branches

## Release Schedule

- **Patch releases**: As needed for critical bugs
- **Minor releases**: Monthly (or when significant features are ready)
- **Major releases**: Yearly (or when breaking changes are necessary)

## Security Releases

For security-related releases:

1. Follow the [SECURITY.md](SECURITY.md) policy
2. Prepare the fix in a private branch
3. Coordinate disclosure with security researchers
4. Release with security advisory
5. Notify users immediately

## Questions?

Contact the maintainers:
- Email: dale@example.com
- GitHub: @claude-php/maintainers

