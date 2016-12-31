all:
	@echo Try sudo make install

install:
	install -d /usr/share/tt-rss/www/plugins/af_feededit
	install -m 644 af_feededit/init.php /usr/share/tt-rss/www/plugins/af_feededit/

.PHONY: all install
