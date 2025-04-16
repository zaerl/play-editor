all: compile

compile:
	php compile.php

clean:
	rm index.html

.PHONY: compile clean
