# smallmd

A flat-file CMS for people who miss the web.

- Drop `.md` files in `content/` → pages appear
- No database. No admin panel. No JavaScript build step.
- PHP 8.1+ and Twig 3. Markdown via `league/commonmark`.
- One `sudo bash setup.sh` on Ubuntu or Debian.

---

## Install

```bash
git clone https://github.com/josh-frank/smallmd
cd smallmd
sudo bash setup.sh [--deploy-user] [--domain]
```

That's it. Visit your server's IP.

---

## Adding content

Create a file in `content/`:

```
content/
  index.md        →  /
  about.md        →  /about
  hello-world.md  →  /hello-world
  projects/
    index.md      →  /projects
```

Every `.md` file can have optional front matter at the top:

```markdown
---
title: My Page
template: post
date: 2025-06-01
nav_order: 2
---

# My Page

Write markdown here.
```

| Field        | Default      | Description                        |
|--------------|--------------|------------------------------------|
| `title`      | First H1     | Page title, shown in `<title>`     |
| `template`   | `page`       | Twig template to use               |
| `date`       | —            | Shown on `post` template           |
| `nav_order`  | `99`         | Sort order in the nav bar          |

---

## Configuration

Edit `config/site.yaml`:

```yaml
title:    My Site
tagline:  words on the internet
theme:    default
author:   Your Name
base_url: https://example.com
cache:    true        # enable Twig cache in production
```

---

## Themes

Themes live in `themes/<name>/`. A theme is just:

```
themes/default/
  templates/
    base.html     ← Twig base layout
    page.html     ← default page template
    post.html     ← blog post template
  assets/
    style.css     ← served at /assets/style.css
```

To use a different theme, set `theme: mytheme` in `config/site.yaml`
and create `themes/mytheme/`.

Templates are Twig 3. Available variables:

```twig
{{ page.title }}      {# page title #}
{{ page.body|raw }}   {# rendered HTML from markdown #}
{{ page.date }}       {# date from front matter #}
{{ page.nav }}        {# array of {slug, title, order} #}
{{ page.meta }}       {# all front matter keys #}
{{ site.title }}      {# from config/site.yaml #}
{{ site.author }}
```

---

## Updating

```bash
cd /var/www/smallmd
git pull
composer install --no-dev --optimize-autoloader
sudo systemctl restart php8.3-fpm
```

---

## Production checklist

- Set `cache: true` in `config/site.yaml`
- Point a domain at the server and update `base_url`
- Add SSL: `sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx`

---

## Structure

```
smallmd/
  content/          ← your .md files go here
  config/
    site.yaml       ← site settings
  lib/
    Config.php      ← yaml config loader
    Page.php        ← page value object
    Parser.php      ← front matter + markdown parser
    Router.php      ← url → file resolver
    Theme.php       ← twig renderer
  public/
    index.php       ← nginx entry point
  themes/
    default/
      assets/
        style.css
      templates/
        base.html
        page.html
        post.html
  var/
    cache/          ← twig cache (if enabled)
  composer.json
  setup.sh
```

---

## Issues

- buildNav() re-reads every .md file on every request — it's called inside Parser::parse(), which runs per page load. Even with Twig caching on, the nav rebuild isn't cached. On a small site this is fine, but it's the one obvious scaling concern.
- Theme::loadFooter() and Parser::renderFooter() are duplicated — Parser has a renderFooter() method that's never called (dead code), and Theme does the same thing privately. One of them should go.
The nginx config hardcodes themes/default/assets/ — so if you change theme: in site.yaml, your assets 404. The asset path should be dynamic or at least use a variable that matches the theme.
- No Content-Security-Policy or other security headers — fine for a hobby project, worth noting for production use.
- html_input: 'allow' in the CommonMark config lets raw HTML through in .md files. That's intentional and useful, but means content authors can inject arbitrary HTML — worth a comment in the config so future maintainers know it's a deliberate choice, not an oversight.
- footer.md front-matter stripping is copy-pasted — the same 5-line YAML-strip block appears three times across Parser and Theme. A small private static helper would clean this up.
- setup.sh sudoers grants apt-get to the deploy user — that's a lot of privilege. git pull, composer install, and systemctl restart are really all a deploy user needs.

---

## License

MIT
