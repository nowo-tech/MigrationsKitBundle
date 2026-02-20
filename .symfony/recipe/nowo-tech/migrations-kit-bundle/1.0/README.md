# Symfony Flex Recipe

This folder contains the **Symfony Flex recipe** for `nowo-tech/migrations-kit-bundle`.

## Automatic installation (when recipe is on the Flex server)

Once this recipe is merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), running:

```bash
composer require nowo-tech/migrations-kit-bundle
```

will automatically:

- Register the bundle in `config/bundles.php`
- Create `config/packages/nowo_migrations_kit.yaml`

## Submitting the recipe to symfony/recipes-contrib

To enable the recipe for everyone:

1. Fork [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib).
2. Copy the contents of `1.0/` to `nowo-tech/migrations-kit-bundle/1.0/` in your fork.
3. Open a pull request.

See [Contributing to Symfony Flex Recipes](https://github.com/symfony/recipes-contrib#contributing).

## Private recipe server

To use this recipe before it is in recipes-contrib, you can set up a [private Flex recipe repository](https://symfony.com/doc/current/setup/flex_private_recipes.html) that points to a repo containing this recipe structure.
