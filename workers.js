export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // GitHub Packagesリスト取得のエンドポイント
    if (url.searchParams.get('do') === 'ghpkglist') {
      return await getGitHubPackagesList(env, url.searchParams.get('user') || 'PTOM76');
    }

    // GitHub Packagesの詳細情報取得のエンドポイント
    if (url.searchParams.get('do') === 'ghpkginfo') {
      const user = url.searchParams.get('user') || 'PTOM76';
      const packageName = url.searchParams.get('package');
      if (packageName) {
        return await getGitHubPackageInfo(env, user, packageName);
      }
      return new Response('Package name is required', { status: 400 });
    }

    let target = new URL('https://pitan76.github.io/maven');
    target.pathname = target.pathname.replace(/\/$/, '') + url.pathname;
    target.search = url.search;

    let newRequest = new Request(target.toString(), request);
    let response = await fetch(newRequest);

    if (response.status === 404) {
      target = new URL('https://maven.pkg.github.com/PTOM76/maven');
      
      target.pathname = target.pathname.replace(/\/$/, '') + url.pathname.replace(/\+/g, '-');
      target.search = url.search;

      let headers = new Headers(request.headers);
      let username = 'pitqn-sub';
      let token = env.GITHUB_TOKEN;
      let basicAuth = 'Basic ' + btoa(`${username}:${token}`);
      headers.set('Authorization', basicAuth);
      
      response = await fetch(target.toString(), {
        method: request.method,
        headers: headers,
        redirect: 'manual',
      });
      return response;
    }

    return response;
  }
};

// GitHub Packagesのリストを取得する関数
async function getGitHubPackagesList(env, user) {
  try {
    const headers = new Headers();
    headers.set('Authorization', `token ${env.GITHUB_TOKEN}`);
    headers.set('Accept', 'application/vnd.github.v3+json');
    headers.set('User-Agent', 'Cloudflare-Workers');

    const response = await fetch(`https://api.github.com/users/${user}/packages?package_type=maven`, {
      headers: headers
    });

    if (!response.ok) {
      return new Response(JSON.stringify({
        error: `GitHub API error: ${response.status} ${response.statusText}`,
        status: response.status
      }), {
        status: response.status,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        }
      });
    }

    const packages = await response.json();
    
    // パッケージ情報を整形
    const formattedPackages = packages.map(pkg => ({
      name: pkg.name,
      package_type: pkg.package_type,
      visibility: pkg.visibility,
      url: pkg.url,
      html_url: pkg.html_url,
      created_at: pkg.created_at,
      updated_at: pkg.updated_at,
      repository: pkg.repository ? {
        name: pkg.repository.name,
        full_name: pkg.repository.full_name,
        html_url: pkg.repository.html_url
      } : null
    }));

    return new Response(JSON.stringify({
      user: user,
      packages: formattedPackages,
      total_count: formattedPackages.length
    }), {
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      }
    });

  } catch (error) {
    return new Response(JSON.stringify({
      error: `Failed to fetch packages: ${error.message}`,
      status: 500
    }), {
      status: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      }
    });
  }
}

// GitHub Packagesの詳細情報を取得する関数
async function getGitHubPackageInfo(env, user, packageName) {
  try {
    const headers = new Headers();
    headers.set('Authorization', `token ${env.GITHUB_TOKEN}`);
    headers.set('Accept', 'application/vnd.github.v3+json');
    headers.set('User-Agent', 'Cloudflare-Workers');

    // パッケージの詳細とバージョン情報を取得
    const [packageResponse, versionsResponse] = await Promise.all([
      fetch(`https://api.github.com/users/${user}/packages/maven/${packageName}`, { headers }),
      fetch(`https://api.github.com/users/${user}/packages/maven/${packageName}/versions`, { headers })
    ]);

    if (!packageResponse.ok) {
      return new Response(JSON.stringify({
        error: `Package not found: ${packageResponse.status} ${packageResponse.statusText}`,
        status: packageResponse.status
      }), {
        status: packageResponse.status,
        headers: {
          'Content-Type': 'application/json',
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Authorization'
        }
      });
    }

    const packageInfo = await packageResponse.json();
    const versions = versionsResponse.ok ? await versionsResponse.json() : [];

    // バージョン情報を整形
    const formattedVersions = versions.map(version => ({
      id: version.id,
      name: version.name,
      description: version.description,
      package_url: version.package_url,
      created_at: version.created_at,
      updated_at: version.updated_at,
      metadata: version.metadata
    }));

    return new Response(JSON.stringify({
      package: {
        name: packageInfo.name,
        package_type: packageInfo.package_type,
        visibility: packageInfo.visibility,
        url: packageInfo.url,
        html_url: packageInfo.html_url,
        created_at: packageInfo.created_at,
        updated_at: packageInfo.updated_at,
        repository: packageInfo.repository
      },
      versions: formattedVersions,
      latest_version: formattedVersions[0] || null
    }), {
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      }
    });

  } catch (error) {
    return new Response(JSON.stringify({
      error: `Failed to fetch package info: ${error.message}`,
      status: 500
    }), {
      status: 500,
      headers: {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization'
      }
    });
  }
}
