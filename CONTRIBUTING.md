# Contributing to MAP-HMS

Thank you for your interest in contributing to MAP-HMS! This guide will help you get started with contributing to our hostel management system.

## Development Workflow

### Branch Naming Convention
- **Features**: `feature/description-of-feature`
- **Bug fixes**: `bugfix/description-of-fix`
- **Documentation**: `docs/description-of-docs`
- **Chores**: `chore/description-of-chore`

Examples:
```bash
feature/outpass-approval-workflow
bugfix/ticket-attachment-upload
docs/api-documentation-update
chore/update-dependencies
```

### Commit Message Format
We use [Conventional Commits](https://www.conventionalcommits.org/) format:

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

**Types**:
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Examples**:
```bash
feat(api): add outpass approval workflow
fix(mobile): resolve offline sync issue
docs(readme): update installation guide
chore(deps): update laravel to 11.0
test(outpass): add approval policy tests
```

## Development Setup

### Prerequisites
- PHP 8.2+ with required extensions
- Node.js 18+ and npm
- Composer 2.x
- SQLite (included with PHP)

### Setup Instructions
1. **Fork and clone** the repository
2. **Setup API**:
   ```bash
   cd api
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```
3. **Setup Mobile**:
   ```bash
   cd mobile
   npm install
   cp app.config.sample.json app.config.json
   ```

### Development Commands
```bash
# API Development
cd api
php artisan serve              # Start development server
php artisan migrate --seed     # Setup database
vendor/bin/pest               # Run tests
php artisan queue:work         # Start queue worker

# Mobile Development
cd mobile
npm start                     # Start Metro bundler
npm run android               # Run on Android
npm run ios                   # Run on iOS
npm test                      # Run tests
```

## Code Standards

### PHP/Laravel Standards
- Follow **PSR-12** coding standards
- Use **Laravel Pint** for code formatting
- Run **PHPStan** for static analysis (level 8)
- Write comprehensive **Pest tests**

### TypeScript/React Native Standards
- Use **TypeScript** with strict mode
- Follow **ESLint** configuration
- Use **Prettier** for code formatting
- Write **Jest tests** for components and stores

### Code Style Commands
```bash
# API code style
cd api
./vendor/bin/pint             # Format PHP code
./vendor/bin/phpstan analyse  # Static analysis

# Mobile code style
cd mobile
npm run lint                  # Lint TypeScript
npm run format                # Format code
npm run type-check            # Type checking
```

## Testing Requirements

### API Testing
- Write **Pest tests** for all new endpoints
- Test **tenant isolation** for multi-tenant features
- Test **authorization policies**
- Include **happy path** and **error cases**

### Mobile Testing
- Write **Jest tests** for new components
- Test **store actions** and **API integration**
- Test **offline functionality**
- Include **E2E tests** for critical flows

### Test Commands
```bash
# API tests
cd api
vendor/bin/pest

# Mobile tests
cd mobile
npm test

# All tests
make test
```

## Multi-Tenancy Guidelines

### Database Design
- **Always include** `tenant_id` in business tables
- Use **global scopes** for automatic tenant filtering
- Create **tenant-scoped indexes** for performance

### Authorization
- Implement **policies** for all resources
- Check **tenant membership** in policies
- Use **middleware** for tenant validation

### Example Implementation
```php
// Model with tenant scope
class OutPass extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new TenantScope);
    }
}

// Policy with tenant check
class OutPassPolicy
{
    public function view(User $user, OutPass $outPass): bool
    {
        return $user->tenant_id === $outPass->tenant_id &&
               $user->can('view_outpasses');
    }
}
```

## Feature Flag Guidelines

### Configuration
- Add feature flags to `config/features.php`
- Use environment variables for toggling
- Document feature flags in code comments

### Implementation
```php
// Check feature flag
if (Feature::isEnabled('laundry_module')) {
    // Feature-specific code
}

// Policy with feature flag
public function viewAny(User $user): bool
{
    return Feature::isEnabled('laundry_module') && 
           $user->can('view_laundry_requests');
}
```

## Security Guidelines

### Authentication & Authorization
- Use **JWT tokens** for API authentication
- Implement **role-based access control**
- Validate **tenant access** in all operations

### Data Protection
- **Encrypt sensitive data** at rest
- Use **HTTPS** for all communications
- Implement **audit logging** for sensitive operations

### Input Validation
- Use **Form Request classes** for validation
- Implement **strict input sanitization**
- Follow **RFC7807** error response format

## Documentation Requirements

### Code Documentation
- Add **PHPDoc** comments to all public methods
- Include **@tenant-scope** annotations where applicable
- Document **feature flags** and **side effects**

### API Documentation
- Update **OpenAPI/Swagger** documentation
- Include **request/response examples**
- Document **authentication requirements**

### User Documentation
- Update **README.md** for significant changes
- Add entries to **CHANGELOG.md**
- Update **knowledge base** documentation

## Pull Request Process

### Before Submitting
1. **Run all tests** and ensure they pass
2. **Check code style** compliance
3. **Update documentation** as needed
4. **Test multi-tenancy** if applicable
5. **Verify security** implications

### PR Checklist
- [ ] Tests added/updated and passing
- [ ] Code style compliance (Pint/ESLint)
- [ ] Multi-tenant isolation verified
- [ ] Security policies enforced
- [ ] Documentation updated
- [ ] Feature flags respected
- [ ] No breaking changes (or documented)

### PR Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests added/updated
- [ ] Integration tests pass
- [ ] Manual testing completed

## Security
- [ ] Tenant isolation maintained
- [ ] Authorization policies updated
- [ ] Input validation added

## Documentation
- [ ] Code comments updated
- [ ] API docs updated
- [ ] User docs updated
```

## Review Process

### Code Review Guidelines
- **Focus on logic** and **security implications**
- Check **multi-tenant isolation**
- Verify **authorization policies**
- Review **test coverage**
- Ensure **performance** considerations

### Review Checklist
- [ ] Code follows standards
- [ ] Tests are comprehensive
- [ ] Security is maintained
- [ ] Documentation is updated
- [ ] No breaking changes
- [ ] Performance is acceptable

## Getting Help

### Resources
- **Documentation**: Browse the [docs/](docs/) directory
- **FAQ**: Check [FAQ](docs/KB/FAQ.md) for common questions
- **Troubleshooting**: See [Troubleshooting Guide](docs/KB/Troubleshooting.md)

### Communication
- **Issues**: Use GitHub issues for bugs and feature requests
- **Discussions**: Use GitHub discussions for questions
- **Security**: Report security issues privately

## Release Process

### Version Numbering
We use [Semantic Versioning](https://semver.org/):
- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist
- [ ] All tests passing
- [ ] Documentation updated
- [ ] Security review completed
- [ ] Performance testing done
- [ ] Release notes prepared

---

Thank you for contributing to MAP-HMS! Your contributions help make hostel management more efficient and secure.

*Contributing guide version: v1.0*
*Owner: MAP Co-Pilot*
