#!/bin/sh

docker compose -f compose.yaml -f compose.override.yaml up -d
