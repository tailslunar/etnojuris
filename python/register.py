from src.adapters.repositories import login_repository
from .exceptions import RegisterBadRequestException, PasswordConfirmationPreconditionFailedException, EmailAlreadyExistsConflictException
from src.utils.default_emails import registration
from random import randint
import hashlib

class PostRegisterService:
    def execute(self, nome_completo, email, data_nascimento, senha, senha_confirmacao):
        self.__check_email_exists(email)
        self.__check_password_confirmation(senha, senha_confirmacao)

        senha = self.__convert_password_to_hash(senha)
        codigo = self.__generate_codigo()
        register_response = login_repository.insert_user(nome_completo, email, data_nascimento, senha, codigo)

        self.__check_registration(register_response)
        self.__send_email(nome_completo, email, codigo)
        response = self.__mount_response()
        return response

    @staticmethod
    def __check_email_exists(email):
        users_response = login_repository.select_email(email)
        if len(users_response) > 0:
            raise EmailAlreadyExistsConflictException

    @staticmethod
    def __check_password_confirmation(senha, senha_confirmacao):
        if senha != senha_confirmacao:
            raise PasswordConfirmationPreconditionFailedException

    @staticmethod
    def __convert_password_to_hash(password):
        sha512 = hashlib.sha512()
        sha512.update(password.encode('utf-8'))
        return sha512.hexdigest()

    @staticmethod
    def __check_registration(register_response):
        if register_response == False:
            raise RegisterBadRequestException

    @staticmethod
    def __generate_codigo():
        return str(randint(000000, 999999)).zfill(6)

    @staticmethod
    def __send_email(nome_completo, email, codigo):
        from src.adapters import email as EmailSMTP
        from src.utils import settings

        text, title = registration.get_email_with_name_email_and_code(nome_completo, settings.SERVER_POINTER, email, codigo)
        EmailSMTP.send_email(email, title, text)

    @staticmethod
    def __mount_response():
        return {
            'register_solicitation': True
        }


post_register_service = PostRegisterService()
