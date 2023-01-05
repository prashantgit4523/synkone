import {Nav, Tab} from "react-bootstrap";
import AppLayout from "../../layouts/app-layout/AppLayout";
import CompanyInfo from "./components/CompanyInfo";
import './style.scss'
import {useEffect, useState} from "react";
import FlashMessages from "../../common/FlashMessages";
import CustomAuthModal from "./components/CustomAuthModal";
import {Inertia} from "@inertiajs/inertia";

function Integrations(props) {
    const [categories, setCategories] = useState([]);
    const [integrations, setIntegrations] = useState([]);
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPageCount, setTotalPageCount] = useState(1);
    const [activeCategoryIndex, setActiveCategoryIndex] = useState(0);
    const [searchKeyword, setSearchKeyword] = useState('');
    const [modalShown, setModalShown] = useState(false);
    const [selectedProvider, setSelectedProvider] = useState(null);
    const [disconnectDisabled, setDisconnectDisabled] = useState(false);


    useEffect(() => {
        return Inertia.on('before', () => setDisconnectDisabled(true));
    }, [])

    useEffect(() => {
        return Inertia.on('finish', () => setDisconnectDisabled(false));
    }, [])

    useEffect(() => {
        document.title = "Integrations";

        let activeCategory = localStorage.getItem('active-category-index');
        
        if (activeCategory != null) {
            setActiveCategoryIndex(activeCategory);
        }
        console.log(activeCategory,activeCategoryIndex,'index');
    },[]);

    useEffect(() => {
        setCategories(props.categories);
        
        if (!searchKeyword.length)
            setIntegrations(setPagination(moveImplementedIntegrationOnTop(props.categories[activeCategoryIndex].integrations)));
        else {
            handleSearchQueryChange(searchKeyword,false);
        }
    }, [activeCategoryIndex, currentPage, props.categories]);

    const handleSearchQueryChange = (keyword, resetPage = true) => {
        const searchTerm = keyword.toLowerCase().trim();

        setSearchKeyword(searchTerm);
        if(resetPage)
            setCurrentPage(1);

        let filteredData = props.categories[activeCategoryIndex].integrations.filter(item => {
            return item.name.toLowerCase().match(new RegExp(searchTerm, 'g'))
        });

        setIntegrations(setPagination(filteredData));
    }

    const setPagination = (paginationData) => {
        if (paginationData.length > 6)
            setTotalPageCount(Math.ceil(((paginationData.length - 6) / 3) + 1));
        else
            setTotalPageCount(1);

        if (currentPage === 1) {
            let perPage = 6;
            return paginationData.slice((currentPage - 1) * perPage, currentPage * perPage);
        } else {
            let perPage = 3;
            return paginationData.slice(0, (currentPage * perPage) + perPage);
        }
    }

    //reset all state on category change
    const resetState = (index) => {
        if(activeCategoryIndex !== index) {
            setCurrentPage(1);
            setTotalPageCount(1);
            localStorage.setItem('active-category-index', index)
            setActiveCategoryIndex(index);
            setSearchKeyword('');
        }
        return true;
    }

    const loadMoreData = () => {
        setCurrentPage(currentPage + 1);

        setTimeout(() => {
            let scrollingElement = (document.scrollingElement || document.body);

            scrollingElement.scrollTop = scrollingElement.scrollHeight;
        },50);

        return true;
    }

    let moveImplementedIntegrationOnTop = (items) => {
        let implementedArray = [];
        let notImplementedArray = [];

        items.map(item => {
            if(item.implemented_integration){
                implementedArray.push(item);
            }else{
                notImplementedArray.push(item);
            }
        });

        return implementedArray.concat(notImplementedArray);
    }

    return (
        <AppLayout>
            <CustomAuthModal
                show={modalShown}
                onClose={() => setModalShown(false)}
                provider={selectedProvider}
            />
            <div id="integration-page">

                <div className="row mt-4 mb-3">
                    <div className="col-md-8">
                        <h4>Categories</h4>
                    </div>
                    <div className="col-md-4 clearfix">
                        <div className="float-end">
                            <div className="ms-lg-3 me-2 me-md-0 mb-3">
                                <div className="row align-items-center">
                                    <div className="col-12">
                                        <input
                                            type="text"
                                            onChange={e => handleSearchQueryChange(e.target.value)}
                                            value={searchKeyword}
                                            placeholder="Search..."
                                            className="form-control form-control-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <Tab.Container id="company-list" activeKey={activeCategoryIndex}>
                    <div className="row">
                        <div className="col-sm-3">
                            <Nav variant="pills" className="flex-column">
                                {
                                    categories.map((category, index) => {
                                        return (
                                            <Nav.Item key={index.toString()}>
                                                <Nav.Link eventKey={category.id} bsPrefix={`nav-link ${activeCategoryIndex == index ? 'active' : ''}`}
                                                          onClick={() => resetState(index)}>{category.name}</Nav.Link>
                                            </Nav.Item>
                                        );
                                    })
                                }
                            </Nav>
                        </div>
                        <div className="col-sm-9">

                            {/* Flash messese */}
                            <FlashMessages/>

                            <Tab.Content>
                                {
                                    categories.map((category, index) => {
                                        return (
                                            <Tab.Pane eventKey={category.id} key={index.toString()}>
                                                <div className="row row-cols-1 row-cols-md-2 row-cols-lg-3 gy-3">
                                                    {integrations.map((company, index) => {
                                                        return (
                                                            <div className="col" key={index.toString()}>
                                                                <CompanyInfo
                                                                    company={company}
                                                                    disconnectDisabled={disconnectDisabled}
                                                                    setSelectedProvider={setSelectedProvider}
                                                                    setModalShown={setModalShown}
                                                                />
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                                {currentPage < totalPageCount && <div className="row mt-4">
                                                    <div className="col-12">
                                                        <div className="text-center">
                                                            <a onClick={() => loadMoreData()}
                                                               className="btn btn-sm btn-primary">
                                                                {/*<i className="mdi mdi-spin mdi-loading me-2"/>*/}
                                                                Load more
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>}
                                            </Tab.Pane>
                                        );
                                    })
                                }
                            </Tab.Content>
                        </div>
                    </div>
                </Tab.Container>
            </div>
        </AppLayout>
    );
}

export default Integrations;