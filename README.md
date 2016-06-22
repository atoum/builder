# atoum builder [![Build Status](https://travis-ci.org/atoum/builder.svg?branch=master)](https://travis-ci.org/atoum/builder)

![atoum](http://downloads.atoum.org/images/logo.png)

## Running locally

To run a local builder instance and hack it around youwill have to use [Docker](https://www.docker.com/) and 
[Compose](https://docs.docker.com/compose/):

```sh
docker-compose up

# OR

docker-compose up -d # This will run the platform as a background daemon
```

Once started, you will be able to reach each service with the following URLs:

* Builder API: `http://localhost:8087`
* Redis:  `redis://localhost:8089`

_Redis does not come with any management console. You can use 
[redis-commander](https://www.npmjs.com/package/redis-commander) if you want to browse the database._

## Building the docker image

The builder platform is shipped and deployed as a docker image. To build it, run:

```sh
docker build -t atoum/builder .
```

## Configuring 

The telemtry platform is configured through environment variables:

| Variable                          | Description                            | Default     | API | Worker |
|-----------------------------------|----------------------------------------|-------------|:---:|:------:|
| `ATOUM_BUILDER_AUTH_TOKEN`        | Authentication token used for webhooks | `null`      | X   |        |
| `ATOUM_BUILDER_PHAR_DIRECTORY`    | Directory used to store PHARs          | `null`      |     | X      |
| `ATOUM_BUILDER_REDIS_HOST`        | Redis host name                        | `localhost` | X   | X      |
| `ATOUM_BUILDER_REDIS_PORT`        | Redis port                             | `6379`      | X   | X      |
| `ATOUM_BUILDER_RESQUE_QUEUE`      | Resque queue name                      | `atoum`     | X   | X      |

## Running

To run the builder platform you will have to boot at least two containers: one for the HTTP API and another for the 
worker:

```sh
docker run --rm --name=atoum-builder-api -p 8087:80 -d atoum/builder
docker run --rm --name=atoum-builder-worker -d --entrypoint=php atoum/builder /app/bin/worker.php
```

**Do not forget to define the required environment variables for each container.**

## Builder API

The API exposes 2 useful routes:

* `POST /hook/push/{token}` is used by [Github's push webhook](https://developer.github.com/v3/activity/events/types/#pushevent)
* `POST /hook/pr/{token}` is used by [Github's pull request webhook](https://developer.github.com/v3/activity/events/types/#pullrequestevent)

There are also two routes to access the API documentation:

* `GET /docs` to get the JSON definition of the API
* `GET /swagger` to reach the Swagger UI
