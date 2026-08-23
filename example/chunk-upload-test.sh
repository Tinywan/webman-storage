#!/usr/bin/env bash
# 大文件分片上传端到端测试脚本：切片 → 逐片上传（md5 校验）→ 查询进度 → 合并 → 校验散列
#
# 前置条件：
#   1. webman 项目已按 example/UploadController.php + example/route.php 注册分片上传路由
#   2. 配置 storage.algo = 'sha1'（默认值），否则最后的散列校验需换成对应算法
#
# 用法：
#   ./chunk-upload-test.sh <待上传文件> [服务地址]
# 环境变量：
#   CHUNK_SIZE  分片大小，默认 5m（需与配置 chunk_size 一致或更小）
#   FILE_ID     文件唯一标识，默认自动生成
set -euo pipefail

FILE="${1:?用法: $0 <待上传文件> [服务地址]}"
BASE_URL="${2:-http://127.0.0.1:8787}"
CHUNK_SIZE="${CHUNK_SIZE:-5m}"
FILE_ID="${FILE_ID:-demo-$(date +%s)}"
FILE_NAME="$(basename "$FILE")"

if [ ! -f "$FILE" ]; then
    echo "❌ 文件不存在：$FILE"
    exit 1
fi

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

echo "==> 切片（${CHUNK_SIZE}/片）..."
split -b "$CHUNK_SIZE" -d "$FILE" "$WORK_DIR/chunk_"
CHUNKS=("$WORK_DIR"/chunk_*)
TOTAL=${#CHUNKS[@]}
echo "    共 ${TOTAL} 片，file_id=${FILE_ID}"

echo "==> 逐片上传..."
for i in "${!CHUNKS[@]}"; do
    CHUNK="${CHUNKS[$i]}"
    MD5="$(md5sum "$CHUNK" | awk '{print $1}')"
    echo "    [$((i + 1))/${TOTAL}] $(basename "$CHUNK") md5=${MD5}"
    curl -sf -F "file=@${CHUNK}" -F "file_id=${FILE_ID}" \
        -F "chunk_index=${i}" -F "chunk_md5=${MD5}" \
        "${BASE_URL}/upload/chunk"
    echo
done

echo "==> 查询上传进度..."
curl -sf "${BASE_URL}/upload/progress?file_id=${FILE_ID}&total_chunks=${TOTAL}"
echo

echo "==> 合并分片..."
curl -sf -F "file_id=${FILE_ID}" -F "total_chunks=${TOTAL}" \
    -F "file_name=${FILE_NAME}" "${BASE_URL}/upload/merge" | tee "$WORK_DIR/merge.json"
echo

echo "==> 校验散列..."
ORIGIN_SHA1="$(sha1sum "$FILE" | awk '{print $1}')"
echo "    原文件 sha1：${ORIGIN_SHA1}"
if grep -q "$ORIGIN_SHA1" "$WORK_DIR/merge.json"; then
    echo "    ✅ 合并产物散列与原文件一致，测试通过"
else
    echo "    ❌ 散列不一致，请检查 merge.json 中的 unique_id"
    exit 1
fi
