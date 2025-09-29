# Imagem leve
FROM python:3.12-slim

# Evita cache de pip e reduz camada
ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

WORKDIR /app
COPY stress.py /app/stress.py
RUN chmod +x /app/stress.py

# Padrões: 500MB de RAM em 60s; CPU off por padrão
ENV MEM_TARGET_MB=500 \
    MEM_RAMP_SECONDS=60 \
    CPU_TARGET_PERCENT=0 \
    CPU_RAMP_SECONDS=60 \
    CPU_THREADS=0 \
    PRINT_EVERY_SEC=2 \
    TOUCH_MEMORY=1 \
    CPU_SLICE_MS=100

ENTRYPOINT ["/app/stress.py"]