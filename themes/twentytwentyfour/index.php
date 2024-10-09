<?php
  get_header();
?>

<div class="p-[30px]">
  <h1 class="font-bold text-[30px]">サンプルフォーム</h1>

  <form method="POST" class="max-w-lg mx-auto p-6 bg-white rounded-md shadow-md space-y-6">
    <!-- 全項目にダミー値を入力するボタン -->
    <div class="text-right mb-4">
      <button type="button" onclick="fillAll()" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">自動入力（デバッグ用）</button>
    </div>

    <!-- 送信種別 (Radio) -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">送信種別</label>
      <div class="flex space-x-4">
        <label class="flex items-center">
          <input type="radio" name="type" value="option1" class="form-radio text-blue-600">
          <span class="ml-2">オプション 1</span>
        </label>
        <label class="flex items-center">
          <input type="radio" name="type" value="option2" class="form-radio text-blue-600">
          <span class="ml-2">オプション 2</span>
        </label>
        <button type="button" onclick="fillValue('type', 'option1')" class="ml-4 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
      </div>
    </div>

    <!-- 名前 -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">名前</label>
      <input type="text" name="name" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('name', '山田 太郎')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- メールアドレス -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">メールアドレス</label>
      <input type="email" name="email" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('email', 'taro@example.com')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- メールアドレス（確認） -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">メールアドレス（確認）</label>
      <input type="email" name="email_confirm" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('email_confirm', 'taro@example.com')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- 電話番号 -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">電話番号</label>
      <input type="tel" name="phone" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('phone', '090-1234-5678')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- URL -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">URL</label>
      <input type="url" name="url" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('url', 'https://example.com')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- 住所 -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">住所</label>
      <input type="text" name="address" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
      <button type="button" onclick="fillValue('address', '東京都渋谷区神南1-1-1')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- 本文 -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">本文</label>
      <textarea name="message" rows="4" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600"></textarea>
      <button type="button" onclick="fillValue('message', 'これはダミーの本文です。')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動入力</button>
    </div>

    <!-- 用途 (ビジネス、個人) -->
    <div>
      <label class="block text-gray-700 font-medium mb-2">用途</label>
      <select name="usage" class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-600">
        <option value="for_business">ビジネス</option>
        <option value="for_personal">個人</option>
      </select>
      <button type="button" onclick="fillValue('usage', 'for_business')" class="mt-2 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動選択</button>
    </div>

    <!-- 同意 (Checkbox) -->
    <div class="flex items-center">
      <input type="checkbox" name="agreement" class="form-checkbox text-blue-600">
      <label class="ml-2 text-gray-700">規約に同意します</label>
      <button type="button" onclick="fillValue('agreement', true)" class="ml-4 bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">自動チェック</button>
    </div>

    <!-- 送信ボタン -->
    <div>
      <button type="submit" name="<?php echo esc_attr(SUBMIT_FORM_ACTION); ?>" class="w-full bg-blue-600 text-white p-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-600">送信</button>
    </div>
  </form>

  <script>
    function fillValue(fieldName, value) {
      const field = document.querySelector(`[name="${fieldName}"]`);

      if (field.type === 'checkbox') {
        field.checked = value;
      } else if (field.type === 'radio') {
        document.querySelector(`[name="${fieldName}"][value="${value}"]`).checked = true;
      } else {
        field.value = value;
      }
    }

    function fillAll() {
      fillValue('type', 'option1');
      fillValue('name', 'VALID TARO');
      fillValue('email', 'taro@example.com');
      fillValue('email_confirm', 'taro@example.com');
      fillValue('phone', '090-1234-5678');
      fillValue('url', 'https://example.com');
      fillValue('address', '1‐1, Chiyoda, Chiyoda-ku, Tokyo');
      fillValue('message', 'これはダミーの本文です。\<script\>console.log(\'test\')\</script\>');
      fillValue('usage', 'for_business');
      fillValue('agreement', true);
    }
  </script>

</div>

<?php get_footer(); ?>