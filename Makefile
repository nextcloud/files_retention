app_name=files_retention

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build/artifacts
appstore_dir=$(build_dir)/appstore
source_dir=$(build_dir)/source
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version+=master

all: appstore

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

clean:
	rm -rf $(build_dir)
	rm -rf node_modules

handlebars:
	handlebars -n OCA.File_Retention.Templates js/template.handlebars -f js/template.js

appstore: clean
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/build \
	--exclude=/CONTRIBUTING.md \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/docs \
	--exclude=/.drone.yml \
	--exclude=/.git \
	--exclude=/.github \
	--exclude=/issue_template.md \
	--exclude=/l10n/l10n.pl \
	--exclude=/README.md \
	--exclude=/.gitattributes \
	--exclude=/.gitignore \
	--exclude=/.l10nignore \
	--exclude=/.php_cs.cache \
	--exclude=/.php_cs.dist \
	--exclude=/psalm.xml \
	--exclude=/screenshots \
	--exclude=/.scrutinizer.yml \
	--exclude=/tests \
	--exclude=/translationfiles \
	--exclude=/.tx \
	--exclude=/vendor \
	--exclude=/Makefile \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi
