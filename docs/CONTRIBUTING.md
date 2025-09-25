# Contributing to Anjem

Thank you for your interest in contributing to Anjem! This document outlines our development workflow, coding standards, and pull request guidelines.

## Git Workflow

### Branch Naming Convention

- `feat/description` - New features
- `fix/description` - Bug fixes
- `docs/description` - Documentation updates
- `refactor/description` - Code refactoring
- `test/description` - Test additions/modifications
- `chore/description` - Maintenance tasks

### Commit Message Format

Follow the conventional commit format:

```
type(scope): brief description

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or modifying tests
- `chore`: Maintenance tasks

**Examples:**
```
feat(auth): add OTP verification for riders
fix(api): resolve ride matching algorithm bug
docs(setup): update Flutter installation guide
```

### Development Process

1. **Create a feature branch** from `main`:
   ```bash
   git checkout main
   git pull origin main
   git checkout -b feat/your-feature-name
   ```

2. **Make your changes** following our coding standards

3. **Test your changes** (see Testing section below)

4. **Commit your changes** with descriptive messages

5. **Push your branch** and create a Pull Request

## Coding Standards

### Flutter/Dart

- Follow [Effective Dart](https://dart.dev/guides/language/effective-dart) guidelines
- Use `dart format` for consistent formatting
- Run `dart analyze` to catch potential issues
- Prefer explicit typing over `var` when type isn't obvious
- Use meaningful variable and function names
- Keep functions small and focused (max 20-25 lines)

**File Structure:**
```
lib/
├── core/           # Shared utilities, constants
├── models/         # Data models
├── services/       # API and business logic
├── widgets/        # Reusable UI components
├── screens/        # Screen/page widgets
├── rider/          # Rider-specific code
└── driver/         # Driver-specific code
```

### Laravel/PHP

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use PHPStan for static analysis
- Follow Laravel naming conventions:
  - Controllers: `PascalCase` ending with `Controller`
  - Models: `PascalCase` (singular)
  - Variables: `camelCase`
  - Methods: `camelCase`
- Use type hints for all method parameters and return types
- Keep controllers thin - move business logic to services

**File Structure:**
```
app/
├── Http/Controllers/   # API controllers
├── Models/            # Eloquent models
├── Services/          # Business logic
├── Repositories/      # Data access layer
├── Jobs/             # Background jobs
└── Events/           # Domain events
```

### Code Quality Requirements

- **Test Coverage**: Minimum 80% for new code
- **Documentation**: All public methods must have docblocks
- **Error Handling**: Proper exception handling and user-friendly error messages
- **Security**: Never commit secrets, validate all inputs
- **Performance**: Consider performance implications (database queries, network calls)

## Pull Request Guidelines

### Before Creating a PR

1. **Run all tests** and ensure they pass:
   ```bash
   # Flutter
   cd mobile && flutter test

   # Laravel
   cd backend && php artisan test
   ```

2. **Run linters** and fix any issues:
   ```bash
   # Flutter
   dart analyze && dart format --set-exit-if-changed .

   # Laravel
   ./vendor/bin/phpstan analyse
   ```

3. **Update documentation** if needed

### PR Requirements

- **Clear title** describing the change
- **Detailed description** explaining what and why
- **Link related issues** using keywords (fixes #123, closes #456)
- **Screenshots** for UI changes
- **Test coverage** for new features
- **No merge conflicts** with main branch

### PR Template

```markdown
## Summary
Brief description of changes

## Changes Made
- [ ] Feature/fix 1
- [ ] Feature/fix 2

## Testing
- [ ] Unit tests added/updated
- [ ] Manual testing completed
- [ ] Edge cases considered

## Screenshots (if applicable)
[Add screenshots for UI changes]

## Related Issues
Fixes #[issue_number]
```

### Review Process

1. **Automated checks** must pass (CI/CD, tests, linting)
2. **Code review** by at least one team member
3. **Manual testing** for significant changes
4. **Approval** required before merge

### Merge Requirements

- All CI checks passing ✅
- At least 1 approval ✅
- No merge conflicts ✅
- Branch up to date with main ✅

## Testing

### Flutter Testing

```bash
# Run all tests
flutter test

# Run tests with coverage
flutter test --coverage
genhtml coverage/lcov.info -o coverage/html
```

### Laravel Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

## Code Review Checklist

### For Authors

- [ ] Code follows project conventions
- [ ] Tests added for new functionality
- [ ] Documentation updated
- [ ] No sensitive data committed
- [ ] Performance considerations addressed
- [ ] Error handling implemented

### For Reviewers

- [ ] Code is readable and well-structured
- [ ] Logic is sound and efficient
- [ ] Tests adequately cover the changes
- [ ] Security implications considered
- [ ] Documentation is accurate
- [ ] Breaking changes are noted

## Getting Help

- **Slack**: #anjem-dev channel
- **Issues**: Create GitHub issues for bugs/features
- **Docs**: Check project documentation in `/docs`
- **Code Questions**: Tag relevant team members in PR comments

## Release Process

1. **Feature freeze** announcement
2. **Release candidate** created from main
3. **Testing phase** (manual + automated)
4. **Bug fixes** on release branch
5. **Production deployment**
6. **Post-release monitoring**

Thank you for contributing to Anjem! 🚗✨