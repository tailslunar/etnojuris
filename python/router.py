router = APIRouter()


@router.get('/login')
async def get_login(email: str, senha: str):
    return get_login_service.execute(email, senha)

@router.post('/register')
async def post_register(nome_completo: str, email: str, data_nascimento: str, senha: str, senha_confirmacao: str):
    return post_register_service.execute(nome_completo, email, data_nascimento, senha, senha_confirmacao)

@router.post('/confirm_register')
async def post_confirm_register(email: str, codigo: int):
    return post_confirm_register_service.execute(email, codigo)

@router.post('/forget_password/email')
async def post_forget_password_email(email: str):
    return post_forget_password_email_service.execute(email)

@router.post('/forget_password/code')
async def post_forget_password_code(email: str, codigo_confirmacao: int):
    return post_forget_password_code_service.execute(email, codigo_confirmacao)

@router.put('/forget_password/new_password')
async def put_forget_password_new_password(email: str, codigo_confirmacao: int, nova_senha: str):
    return put_forget_password_new_password_service.execute(email, codigo_confirmacao, nova_senha)

@router.delete('/delete_login')
async def delete_login(model: DeleteLoginModel):
    return delete_login_service.execute(model)
