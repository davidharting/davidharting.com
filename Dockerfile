FROM node:19.8-bullseye as builder

RUN apt-get update; apt install -y curl python-is-python3 pkg-config build-essential

RUN mkdir /app
WORKDIR /app

COPY . .

RUN npm install --production=false && npm run build
WORKDIR /app/build
RUN npm ci


FROM node:19.8-bullseye

LABEL fly_launch_runtime="nodejs"

COPY --from=builder /app/build /build

WORKDIR /build
ENV NODE_ENV production

CMD [ "node", "server.js"]
