# DocBundle

## Integration with Asciinema (ciine)

Workflow for ciine after installing the bundle.

set up the filename template.  This is global

set up .bashrc so that rec or ciine runs

```bash
export CIINE_PATH=~/g/sites/showcase/casts
export CIINE_PATH=~/g/sites/showcase/casts/${PWD##*/}/$(date '+%s').cast
ciine rec $CIINE_PATH
```





Symfony Bundle that provides some utilities for creating Spinx documentation for a Symfony project.

First, setup Sphinx

```bash

sudo apt-get install python3-sphinx
pipx install sphinx_rtd_theme
pipx install sphinx_fontawesome
```

```bash
composer req survos/doc-bundle
```

Put the .rst.twig files in templates/docs.


```twig
{# index.rst.twig #}
{{ rst_h(1, 'Welcome to Jardinio!') }}

The goal of this project is to provide plant management for botanical gardens, primarily involving
inventory (including samples and seeds) and maybe visitor tours.  It uses QR codes throughout the system.

.. toctree::
    :maxdepth: 2
    :caption: Contents:

.. fa:: check

{{ rst_h(2,'Administration') }}

The administrative portal allows managers to gardens


.. toctree::
    :maxdepth: 1

    quick-start
    tutorial

Indices and tables
==================

* :ref:`genindex`
* :ref:`search`

```

cat tutorial.rst.twig
```bash
{{ rst_h(1, 'Tutorial') }}

{{ rst_h(2, 'create_account'|trans|title) }}
{{ rst_h(2, 'create_project'|trans|title) }}
{{ rst_h(2, 'add_a_plant'|trans|title) }}



{{ rst_h(3, 'add_a_plant'|trans|title) }}

.. fa:: check
```


```bash
bin/console survos:build-docs
cd docs
make html
```
