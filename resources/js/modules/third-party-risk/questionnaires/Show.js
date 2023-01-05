import React, { useEffect, useState, useRef } from "react";

import { usePage } from "@inertiajs/inertia-react";

import Question from "../components/Question";
import ReactPaginate from "react-paginate";
import AuthLayout from "../../../layouts/auth-layout/AuthLayout";
import Logo from "../../../layouts/auth-layout/components/Logo";
import ContentLoader from "../../../common/content-loader/ContentLoader";

import "../style/questionnaires.scss";
import '../../../common/react-pagination/react-pagination.scss';
import { Alert } from "react-bootstrap";
import ReactTooltip from "react-tooltip";

const Show = () => {
    const perPage = 6;
    const [questionsList, setQuestionsList] = useState([]);
    const [page, setPage] = useState(0);
    const { vendor, questions, token, can_respond } = usePage().props;
    const [answers, setAnswers] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState(null);
    const pageCount = Math.ceil(questions.length / perPage);
    const myRef = React.useRef(null);
    const [disableNext, setDisableNext] = useState(true);
    const [disableSubmit, setDisableSubmit] = useState(true);
    const [unansweredCount, setUnanswerdCount] = useState(questions.length);
    const [answeredCount, setAnswerdCount] = useState(0);
    const [answeredPage, setAnswerdPage] = useState(1);
    const paginationRef = useRef(null);
    const [loading, setLoading] = useState(false);
    
    useEffect(() => {
        if(page > answeredPage)
        {
            console.log('Max Page: '+page)
            setAnswerdPage(page)
        }
        setQuestionsList(questions.slice(page * perPage, (page + 1) * perPage));
        
        if((questions.length - ((page + 1)*perPage)) >= unansweredCount)
            setDisableNext(false);
        else
            setDisableNext(true);

        var unansweredQuestionsCount = answers.filter(a => a.answer === null).length;
        var els = document.getElementsByClassName("page-item");

        if(unansweredQuestionsCount == 0)
        {
            setDisableNext(true);
            for(var i = 0; i < els.length; i++)
            {
                if(i > 1)
                {
                    els[i].classList.add('disabled');
                    els[i].setAttribute('data-tip', 'Complete all questions to go to the next page.');
                    els[i].setAttribute('currentitem', 'false');
                }
                else
                {
                    els[i].classList.remove('disabled');
                    els[i].removeAttribute('data-tip');
                }
            }
        }

        if(unansweredQuestionsCount != 0)
        {
            if((questions.length - ((page + 1)*perPage)) >= unansweredQuestionsCount)
            {
                setDisableNext(false);
            }
            else
                setDisableNext(true);
        }

        if(unansweredQuestionsCount == 0)
        {
            setDisableNext(false);
        }

    }, [page]);

    useEffect(() => {
        setAnswers(questions.map(q => ({ question_id: q.id, answer: q.single_answer?.answer ?? null })));
    }, []);

    useEffect(() => {
        var unansweredQuestionsCount = answers.filter(a => a.answer === null).length;
        setUnanswerdCount(unansweredQuestionsCount);

        if(unansweredQuestionsCount != 0)
        {
            setDisableSubmit(true);
            if((questions.length - ((page + 1)*perPage)) >= unansweredQuestionsCount)
            {
                setDisableNext(false);
            }
            else
                setDisableNext(true);
        }
        else{
            setDisableSubmit(false);
        }
        ReactTooltip.rebuild();
    }, [answers]);

    const handleSetPage = page => {
        paginationRef.current.forcePage = page;
        setPage(page);
        
        window.scrollTo({
            behavior: 'smooth',
            top: myRef.current.offsetTop - 30
        });
    }

    const getAnswer = id => answers.find(a => a.question_id === id)?.answer;
    const setAnswer = (id) => (answer) => {
        setAnswers(prevState => prevState.map(a => a.question_id === id ? ({ ...a, answer }) : a));
    }

    const handleSubmit = () => {
        setProcessing(true);
        setError(null);
        setLoading(true);
        const unansweredQuestionsCount = answers.filter(a => a.answer === null).length;
        if (unansweredQuestionsCount > 0) {
            window.scrollTo({ behavior: 'smooth', top: 0 });
            setError(`You still have ${unansweredQuestionsCount} unanswered questions!`);
            setProcessing(false);
            return;
        }
        axiosFetch.post(route('third-party-risk.save-questionnaire',), { answers, token })
            .then(() => {
                setProcessing(false);
                setSubmitted(true);
                setLoading(false);
            })
            .catch(function (e) {
                setLoading(false);
            });
    }

    useEffect(() => {        
        var els = document.getElementsByClassName("page-item");
        const list = [...document.querySelectorAll('.page-item')];
        const active = document.querySelector('.page-item.active');
        const activeIndex = list.indexOf(active);
        console.log(activeIndex);
        var tempPage2 = answeredPage;
        for(var i = 0; i < els.length; i++)
        {
            const tempPage = els[i].childNodes[0].innerHTML;
            
            if(answeredPage != 1)
                tempPage2 = answeredPage + 1;

            if(tempPage <= tempPage2)
            {
                els[i].classList.remove('disabled');
                els[i].removeAttribute('data-tip');
            }
            else
            {
                els[i].classList.add('disabled');
                els[i].setAttribute('data-tip', 'Complete all questions to go to the next page.');
                els[i].setAttribute('currentitem', 'false');
            }
        }
        ReactTooltip.rebuild();
    }, [questionsList]);

    if (!can_respond) return (
        <AuthLayout>
            <div className="card bg-pattern">
                <Logo />
                <div className="card-body pb-0">
                    <div className="row" id="questionnaire">
                        <div className="col-12 m-30 title-heading text-center">
                            <h5 className="card-title">Hi {vendor.contact_name},</h5>
                            <p>
                                You cannot respond to this vendor risk questionnaire because you already submitted your
                                answers.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
    )
    return (

        <AuthLayout>
            {error ? (
                <Alert variant="danger" onClose={() => setError(null)} dismissible>
                    {error}
                </Alert>
            ) : null}
            <ContentLoader
                show={loading}
            >
            <div className="card bg-pattern">
                <Logo />
                <div className="card-body pb-0">
                    <div className="row" id="questionnaire">
                        <div className="col-12 m-30 title-heading text-center">
                            <h5 className="card-title">Hi {vendor.contact_name},</h5>
                            {submitted ? (
                                <p>Your response has been recorded, Thank you.</p>
                            ) : (
                                <p>
                                    You have been invited to complete this vendor
                                    risk questionnaire. Please read the questions
                                    carefully and provide your answers.
                                </p>
                            )}
                        </div>
                    </div>
                    {!submitted ? (
                        <div ref={myRef}>
                            <ol>
                                {questionsList.map(({ text, id }) =>
                                    <Question
                                        id={id}
                                        question={text}
                                        answer={getAnswer(id)}
                                        setAnswer={setAnswer(id)}
                                        key={id}
                                    />
                                )}
                            </ol>
                            {/* <div className="mt-3">
                                        <ReactPaginate
                                            className="react-pagination pagination pagination-rounded justify-content-center"
                                            nextLabel="&raquo;"
                                            onPageChange={({selected}) => handleSetPage(selected)}
                                            forcePage={page}
                                            marginPagesDisplayed={1}
                                            pageCount={pageCount}
                                            previousLabel="&laquo;"
                                            pageClassName="page-item"
                                            pageLinkClassName="page-link"
                                            previousClassName="page-item d-none"
                                            previousLinkClassName="page-link"
                                            nextClassName="page-item d-none"
                                            nextLinkClassName="page-link"
                                            breakLabel="..."
                                            breakClassName="page-item"
                                            breakLinkClassName="page-link"
                                            containerClassName="pagination"
                                            activeClassName="active"
                                            renderOnZeroPageCount={null}
                                        />
                                    </div> */}
                            <div className="d-md-flex d-block text-center justify-content-md-between justify-content-center flex-wrap align-items-center mt-3 mb-3">
                                {page > 0 ?
                                    <button className="btn btn-primary" rel="prev"
                                        onClick={() => handleSetPage(page - 1)}>Previous</button> :
                                    <div />}
                                    {/* <p>Page: {page}, Answered: {questions.length - unansweredCount}</p> */}
                                <ReactPaginate
                                    className="react-pagination pagination pagination-rounded justify-content-center align-items-center my-2 my-md-0"
                                    nextLabel="&raquo;"
                                    onPageChange={({ selected }) => handleSetPage(selected)}
                                    forcePage={page}
                                    marginPagesDisplayed={1}
                                    pageCount={pageCount}
                                    previousLabel="&laquo;"
                                    pageClassName="page-item"
                                    pageLinkClassName="page-link"
                                    previousClassName="page-item d-none"
                                    previousLinkClassName="page-link"
                                    nextClassName="page-item d-none"
                                    nextLinkClassName="page-link"
                                    breakLabel="..."
                                    breakClassName="page-item"
                                    breakLinkClassName="page-link"
                                    containerClassName="pagination"
                                    activeClassName="active"
                                    renderOnZeroPageCount={null}
                                    ref={paginationRef}
                                    nextPageRel="null"
                                />
                                
                                {page === pageCount - 1 ? (
                                    !disableSubmit  ? (
                                        <button className="btn btn-primary"
                                            onClick={handleSubmit}
                                            disabled={processing}>Submit</button>
                                    ) : (
                                        <button className="btn btn-primary disabled-btn"
                                            data-tip="Complete all questions to submit">Submit</button>)
                                ) : (
                                    !disableNext  ? (
                                        <button className="btn btn-primary" rel="next"
                                            onClick={() => handleSetPage(page + 1)}>Next</button>
                                    ) : (
                                        <button className="btn btn-primary disabled-btn"
                                            data-tip="Complete all questions to go to the next page.">Next</button>)
                                )}
                                <ReactTooltip />
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>
            </ContentLoader>
        </AuthLayout>

    );
}
export default Show;
