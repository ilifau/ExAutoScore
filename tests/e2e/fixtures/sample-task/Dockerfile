# Minimal test image for the ExAutoScore browser tests
#   IN : submitted files in /user (single file or required.tgz)
#   OUT: /result/results.json
FROM python:3.12-slim

ENV PROVIDEDDIR=/files
ENV REQUIREDDIR=/user
ENV RESULTDIR=/result
ENV WORKDIR=/work

RUN mkdir -p $RESULTDIR $WORKDIR && chmod 777 $RESULTDIR $WORKDIR
WORKDIR $WORKDIR
ADD $PROVIDEDDIR/* ./
RUN if [ -f provided.tgz ]; then tar xzf provided.tgz; rm provided.tgz; fi
