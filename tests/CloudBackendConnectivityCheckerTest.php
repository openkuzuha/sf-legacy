<?php

use App\Settings\CloudBackendConnectivityChecker;

// 実際のValkey/MinIOが起動していない環境（CIなど）でも決定的に通るよう、
// 到達確認は「確実に繋がらない」宛先に対する失敗パスのみ検証する。
// 成功パスは実インフラが必要になるため、この自動テストの対象外とする
// （ValkeyPostRepositoryやS3PostArchiveなど他のクラウド系実装と同じ扱い）。
test('ValkeyにもS3互換ストレージにも到達できない場合は両方の問題を報告する', function () {
    $checker = new CloudBackendConnectivityChecker(
        valkeyUrl: 'redis://127.0.0.1:1',
        s3Endpoint: 'http://127.0.0.1:1',
        s3Region: 'us-east-1',
        s3Bucket: 'bbs-archive',
        s3AccessKey: 'dummy',
        s3SecretKey: 'dummy',
        s3PathStyle: true,
    );

    $problems = $checker->check();

    expect($problems)->toHaveCount(2);
    expect($problems[0])->toContain('Valkey');
    expect($problems[1])->toContain('S3互換ストレージ');
});
