<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

class UserRepository implements UserRepositoryInterface
{
    private User $user;

    /**
     * User モデルを注入する
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }



    /**
     * id でユーザーを取得する（見つからなければ null）
     *
     * @param string $id
     * @return array|null
     */
    public function getData(string $id): ?array
    {
        //
        $user = $this->user->where('id', $id)->first();

        //
        return $user?->toArray();
    }



    /**
     * ユーザーを作成する（password は User の hashed キャストで自動ハッシュ化される）
     *
     * @param array $createData family_name / given_name / email / password
     * @return string 作成したユーザーの id
     */
    public function create(array $createData): string
    {
        //
        $result = $this->user->create($createData);

        //
        return (string)$result['id'];
    }



    /**
     * ユーザーを更新する（渡された項目だけ。どの項目を更新してよいかは Service が決める）
     *
     * @param string $id
     * @param array $updateData
     * @return void
     */
    public function update(string $id, array $updateData): void
    {
        //
        $this->user->where('id', $id)->update($updateData);
    }
}
